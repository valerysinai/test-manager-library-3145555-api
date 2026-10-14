<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\{BookPage};
use App\Library\Application\Ports\BookRepository;
use App\Library\Domain\{Book, Isbn};
use App\Library\Domain\Errors\{ConcurrentModification, IsbnTaken};
use PDOException;
final readonly class PgBookRepository implements BookRepository
{
    private const string SELECT = 'SELECT b.*, b.xmin::text AS witness,c.name AS category_name FROM library.book b JOIN library.category c ON c.id=b.category_id';
    public function __construct(private PgSession $session) {}
    private function mapped(array $row): Book
    {
        $this->session->versions[$row['id']] = $row['witness'];
        return Rows::book($row);
    }
    public function findActive(string $id): ?Book
    {
        $r = $this->session->one(self::SELECT.' WHERE b.id=:id AND b.deleted_at IS NULL', ['id' => $id]);
        return $r === null ? null : $this->mapped($r);
    }
    public function findActiveByIsbn(Isbn $isbn): ?Book
    {
        $r = $this->session->one(self::SELECT.' WHERE b.isbn=:isbn AND b.deleted_at IS NULL', ['isbn' => $isbn->value]);
        return $r === null ? null : $this->mapped($r);
    }
    public function findActiveMany(array $ids): array
    {
        if ($ids === []) { return []; }
        $p = []; $marks = [];
        foreach (array_values($ids) as $index => $id) { $p['id'.$index] = $id; $marks[] = ':id'.$index; }
        $result = [];
        foreach ($this->session->all(self::SELECT.' WHERE b.deleted_at IS NULL AND b.id IN ('.implode(',', $marks).') ORDER BY b.id', $p) as $r) {
            $result[$r['id']] = $this->mapped($r);
        }
        return $result;
    }
    private function parameters(Book $book): array
    {
        return ['id' => $book->id, 'title' => $book->title, 'author' => $book->author, 'isbn' => $book->isbn->value,
            'category' => $book->category->id, 'total' => $book->totalCopies->value, 'available' => $book->availableCopies,
            'cover' => $book->coverKey, 'deleted' => $book->deletedAt === null ? null : PgSession::date($book->deletedAt)];
    }
    public function add(Book $book): void
    {
        try {
            $this->session->write('INSERT INTO library.book (id,title,author,isbn,category_id,total_copies,available_copies,cover_key,deleted_at)
                VALUES (:id,:title,:author,:isbn,:category,:total,:available,:cover,:deleted)', $this->parameters($book));
        } catch (PDOException $e) {
            if ($e->getCode() === '23505' && str_contains($e->errorInfo[2] ?? '', 'uq_book_active_isbn')) { throw new IsbnTaken; }
            throw $e;
        }
    }
    public function save(Book $book): void
    {
        if (!$this->session->pdo()->inTransaction() || !isset($this->session->versions[$book->id])) {
            throw new \LogicException('La escritura de libro requiere lectura versionada dentro de la transacción.');
        }
        $p = $this->parameters($book);
        unset($p['isbn']);
        $p['version'] = $this->session->versions[$book->id];
        $affected = $this->session->write('UPDATE library.book SET title=:title,author=:author,category_id=:category,
            total_copies=:total,available_copies=:available,cover_key=:cover,deleted_at=:deleted
            WHERE id=:id AND xmin::text=:version', $p);
        if ($affected !== 1) { throw new ConcurrentModification; }
        unset($this->session->versions[$book->id]);
    }
    public function hasOpenLoans(string $bookId): bool
    {
        return $this->session->one('SELECT id FROM library.loan_item WHERE book_id=:id AND returned_at IS NULL LIMIT 1', ['id' => $bookId]) !== null;
    }
    public function search(string $query, ?string $categoryId, int $page, int $size): BookPage
    {
        $where = "b.deleted_at IS NULL AND (b.title ILIKE :q1 ESCAPE '!' OR b.author ILIKE :q2 ESCAPE '!')";
        $p = ['q1' => PgSession::pattern($query), 'q2' => PgSession::pattern($query)];
        if ($categoryId !== null) { $where .= ' AND b.category_id=:category'; $p['category'] = $categoryId; }
        $total = (int) $this->session->one('SELECT count(*) AS total FROM library.book b WHERE '.$where, $p)['total'];
        $rows = $this->session->all(self::SELECT.' WHERE '.$where.' ORDER BY b.title,b.id LIMIT :size OFFSET :offset',
            $p + ['size' => $size, 'offset' => ($page - 1) * $size]);
        return new BookPage(array_map(Rows::book(...), $rows), $page, $size, $total);
    }
}
