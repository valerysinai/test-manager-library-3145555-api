<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\BookPage;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\CategoryRepository;
use App\Library\Domain\Book;
use App\Library\Domain\Category;
use App\Library\Domain\Isbn;
use App\Library\Domain\Errors\IsbnTaken;
use App\Library\Domain\Errors\ConcurrentModification;
final class InMemoryCatalog implements BookRepository, CategoryRepository
{
    public array $books = [];
    public array $categories = [];
    public array $openLoans = [];
    public array $events = [];
    public int $reads = 0;
    public int $failSaves = 0;
    public bool $failAdd = false;
    public bool $inTransaction = false;
    public ?\Closure $afterConflict = null;
    public function __construct()
    {
        $this->categories[DomainFixtures::category()->id] = DomainFixtures::category();
        $science = new Category('22222222-2222-4222-8222-222222222222', 'Ciencia');
        $this->categories[$science->id] = $science;
    }
    public function find(string $id): ?Category { $this->reads++; return $this->categories[$id] ?? null; }
    public function all(): array { return array_values($this->categories); }
    public function findActiveMany(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) { $book = $this->findActive($id); if ($book !== null) { $result[$id] = $book; } }
        return $result;
    }
    public function findActive(string $id): ?Book
    {
        $this->reads++;
        $book = $this->books[$id] ?? null;
        return $book?->deletedAt === null ? $book : null;
    }
    public function findActiveByIsbn(Isbn $isbn): ?Book
    {
        $this->reads++;
        foreach ($this->books as $book) {
            if ($book->deletedAt === null && $book->isbn->value === $isbn->value) { return $book; }
        }
        return null;
    }
    public function add(Book $book): void
    {
        $this->assertTransaction();
        if ($this->failAdd || $this->findActiveByIsbn($book->isbn) !== null) { throw new IsbnTaken; }
        if (isset($this->books[$book->id])) { throw new \LogicException('Identidad de libro repetida.'); }
        $this->books[$book->id] = $book;
    }
    public function save(Book $book): void
    {
        $this->assertTransaction();
        if ($this->failSaves > 0) { $this->failSaves--; throw new ConcurrentModification; }
        $this->events[] = $book->coverKey === null ? 'save-null' : 'save-cover';
        $this->books[$book->id] = $book;
    }
    public function hasOpenLoans(string $bookId): bool
    {
        $this->assertTransaction();
        return $this->openLoans[$bookId] ?? false;
    }
    public function search(string $query, ?string $categoryId, int $page, int $size): BookPage
    {
        $this->reads++;
        $found = array_values(array_filter($this->books, static fn (Book $book): bool =>
            $book->deletedAt === null && ($categoryId === null || $book->category->id === $categoryId)
            && ($query === '' || mb_stripos($book->title, $query, 0, 'UTF-8') !== false
                || mb_stripos($book->author, $query, 0, 'UTF-8') !== false)));
        usort($found, static fn (Book $a, Book $b): int => [$a->title, $a->id] <=> [$b->title, $b->id]);
        return new BookPage(array_slice($found, ($page - 1) * $size, $size), $page, $size, count($found));
    }
    private function assertTransaction(): void
    {
        if (!$this->inTransaction) { throw new \LogicException('La escritura requiere unidad de trabajo.'); }
    }
}
