<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\{LoanPage, LoanQuery};
use App\Library\Application\Ports\LoanRepository;
use App\Library\Domain\{Loan, LoanItem};
use App\Library\Domain\Errors\ItemAlreadyReturned;
use DateTimeImmutable;
final readonly class PgLoanRepository implements LoanRepository
{
    public function __construct(private PgSession $session) {}
    public function lockMember(string $memberId): void { $this->session->lock($memberId); }
    public function memberId(string $loanId): ?string { return $this->session->one('SELECT member_id FROM library.loan WHERE id=:id', ['id' => $loanId])['member_id'] ?? null; }
    private function hydrate(array $rows): array
    {
        if ($rows === []) { return []; }
        $p = []; $marks = [];
        foreach ($rows as $i => $row) { $p['id'.$i] = $row['id']; $marks[] = ':id'.$i; }
        $groups = [];
        foreach ($this->session->all('SELECT * FROM library.loan_item WHERE loan_id IN ('.implode(',', $marks).') ORDER BY id', $p) as $item) {
            $groups[$item['loan_id']][] = $item;
        }
        return array_map(static fn ($r) => Rows::loan($r, $groups[$r['id']] ?? []), $rows);
    }
    public function find(string $id): ?Loan
    {
        return $this->hydrate($this->session->all('SELECT * FROM library.loan WHERE id=:id', ['id' => $id]))[0] ?? null;
    }
    public function forMember(string $memberId): array { return $this->hydrate($this->session->all('SELECT * FROM library.loan WHERE member_id=:id', ['id' => $memberId])); }
    public function add(Loan $loan): void
    {
        if (!$this->session->pdo()->inTransaction()) { throw new \LogicException('El préstamo requiere transacción.'); }
        $this->session->write('INSERT INTO library.loan (id,member_id,loaned_by_user_id,loaned_at) VALUES (:id,:member,:operator,:at)',
            ['id' => $loan->id, 'member' => $loan->memberId, 'operator' => $loan->loanedByUserId, 'at' => PgSession::date($loan->loanedAt)]);
        foreach ($loan->items as $item) {
            $this->session->write('INSERT INTO library.loan_item (id,loan_id,book_id,book_title,isbn,category_name,due_at,returned_at)
                VALUES (:id,:loan,:book,:title,:isbn,:category,:due,:returned)',
                ['id' => $item->id, 'loan' => $item->loanId, 'book' => $item->bookId, 'title' => $item->bookTitle,
                    'isbn' => $item->isbn->value, 'category' => $item->categoryName, 'due' => PgSession::date($item->dueAt),
                    'returned' => $item->returnedAt === null ? null : PgSession::date($item->returnedAt)]);
        }
    }
    public function markReturned(LoanItem $item): void
    {
        if (!$this->session->pdo()->inTransaction() || $item->returnedAt === null) { throw new \LogicException('La devolución requiere transacción e instante.'); }
        if ($this->session->write('UPDATE library.loan_item SET returned_at=:at WHERE id=:id AND loan_id=:loan AND returned_at IS NULL',
            ['at' => PgSession::date($item->returnedAt), 'id' => $item->id, 'loan' => $item->loanId]) !== 1) {
            throw new ItemAlreadyReturned('La línea ya fue devuelta.');
        }
    }
    public function search(LoanQuery $query, DateTimeImmutable $now): LoanPage
    {
        $parts = ['TRUE']; $p = [];
        if ($query->from !== null) { $parts[] = 'l.loaned_at >= :from'; $p['from'] = PgSession::date($query->from); }
        if ($query->toExclusive !== null) { $parts[] = 'l.loaned_at < :to'; $p['to'] = PgSession::date($query->toExclusive); }
        if ($query->memberId !== null) { $parts[] = 'l.member_id=:member'; $p['member'] = $query->memberId; }
        if ($query->status !== null) {
            $parts[] = "(CASE WHEN NOT EXISTS (SELECT 1 FROM library.loan_item i WHERE i.loan_id=l.id AND i.returned_at IS NULL) THEN 'closed'
                WHEN EXISTS (SELECT 1 FROM library.loan_item i WHERE i.loan_id=l.id AND i.returned_at IS NULL AND i.due_at < CAST(:now AS timestamptz))
                THEN 'overdue' ELSE 'open' END)=:status";
            $p['now'] = PgSession::date($now); $p['status'] = $query->status;
        }
        $where = implode(' AND ', $parts);
        $total = (int) $this->session->one('SELECT count(*) AS total FROM library.loan l WHERE '.$where, $p)['total'];
        if ($total === 0 || $query->page > intdiv($total - 1, $query->size) + 1) {
            return new LoanPage([], $query->page, $query->size, $total);
        }
        $rows = $this->session->all('SELECT l.* FROM library.loan l WHERE '.$where.' ORDER BY l.loaned_at DESC,l.id LIMIT :size OFFSET :offset',
            $p + ['size' => $query->size, 'offset' => ($query->page - 1) * $query->size]);
        return new LoanPage($this->hydrate($rows), $query->page, $query->size, $total);
    }
    public function overdue(DateTimeImmutable $now): array
    {
        $rows = $this->session->all('SELECT l.id AS loan_id,i.id AS item_id,i.book_title,m.full_name AS member_name,i.due_at,
            floor(extract(epoch FROM (CAST(:now AS timestamptz)-i.due_at))/86400)::integer AS days_late
            FROM library.loan_item i JOIN library.loan l ON l.id=i.loan_id JOIN library.member m ON m.id=l.member_id
            WHERE i.returned_at IS NULL AND i.due_at < CAST(:cutoff AS timestamptz)
            ORDER BY days_late DESC,i.due_at,i.id', ['now' => PgSession::date($now), 'cutoff' => PgSession::date($now)]);
        foreach ($rows as &$r) { $r['days_late'] = (int) $r['days_late']; $r['due_at'] = (new DateTimeImmutable($r['due_at']))->format('Y-m-d\TH:i:s\Z'); }
        return $rows;
    }
}
