<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\MemberRepository;
use App\Library\Application\MemberPage;
use App\Library\Domain\{DocumentNumber, Member};
use App\Library\Domain\Errors\DocumentTaken;
use PDOException;
final readonly class PgMemberRepository implements MemberRepository
{
    public function __construct(private PgSession $session) {}
    public function find(string $id): ?Member
    {
        $r = $this->session->one('SELECT * FROM library.member WHERE id=:id', ['id' => $id]);
        return $r === null ? null : Rows::member($r);
    }
    public function findByDocument(DocumentNumber $number): ?Member
    {
        $r = $this->session->one('SELECT * FROM library.member WHERE document_number=:number', ['number' => $number->value]);
        return $r === null ? null : Rows::member($r);
    }
    public function add(Member $member): void
    {
        try {
            $this->session->write('INSERT INTO library.member (id,full_name,document_number) VALUES (:id,:name,:number)',
                ['id' => $member->id, 'name' => $member->fullName, 'number' => $member->documentNumber->value]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505' && str_contains($e->errorInfo[2] ?? '', 'uq_member_document_number')) { throw new DocumentTaken; }
            throw $e;
        }
    }
    public function search(string $query, int $page, int $size): MemberPage
    {
        $where = "full_name ILIKE :q1 ESCAPE '!' OR document_number ILIKE :q2 ESCAPE '!'";
        $p = ['q1' => PgSession::pattern($query), 'q2' => PgSession::pattern($query)];
        $total = (int) $this->session->one('SELECT count(*) AS total FROM library.member WHERE '.$where, $p)['total'];
        $rows = $this->session->all('SELECT * FROM library.member WHERE '.$where.' ORDER BY full_name,id LIMIT :size OFFSET :offset',
            $p + ['size' => $size, 'offset' => ($page - 1) * $size]);
        return new MemberPage(array_map(Rows::member(...), $rows), $page, $size, $total);
    }
    public function countUnreturnedBooks(string $memberId): int
    {
        return (int) $this->session->one('SELECT count(*) AS total FROM library.loan_item i JOIN library.loan l ON l.id=i.loan_id
            WHERE l.member_id=:id AND i.returned_at IS NULL', ['id' => $memberId])['total'];
    }
}
