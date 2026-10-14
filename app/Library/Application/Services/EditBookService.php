<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\CategoryRepository;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\Book;
use App\Library\Domain\Quantity;
use App\Library\Domain\User;
final readonly class EditBookService
{
    public function __construct(private BookRepository $books, private CategoryRepository $categories, private UnitOfWork $work) {}
    public function execute(User $actor, string $id, string $title, string $author, string $categoryId, int $total): Book
    {
        CatalogRules::admin($actor);
        return $this->work->run(function () use ($id, $title, $author, $categoryId, $total): Book {
            $book = CatalogRules::book($this->books, $id)->revise($title, $author,
                CatalogRules::category($this->categories, $categoryId), new Quantity($total));
            $this->books->save($book);
            return $book;
        });
    }
}
