<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\CategoryRepository;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\Book;
use App\Library\Domain\Isbn;
use App\Library\Domain\Quantity;
use App\Library\Domain\User;
use App\Library\Domain\Errors\IsbnTaken;
final readonly class CreateBookService
{
    public function __construct(private BookRepository $books, private CategoryRepository $categories, private UnitOfWork $work) {}
    /** El UUID lo asigna el servidor, nunca la petición HTTP. */
    public function execute(User $actor, string $id, string $title, string $author, string $isbn, string $categoryId, int $total): Book
    {
        CatalogRules::admin($actor);
        return $this->work->run(function () use ($id, $title, $author, $isbn, $categoryId, $total): Book {
            $book = Book::create($id, $title, $author, new Isbn($isbn),
                CatalogRules::category($this->categories, $categoryId), new Quantity($total));
            if ($this->books->findActiveByIsbn($book->isbn) !== null) { throw new IsbnTaken; }
            $this->books->add($book);
            return $book;
        });
    }
}
