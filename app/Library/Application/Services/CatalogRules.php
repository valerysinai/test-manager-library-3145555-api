<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\CategoryRepository;
use App\Library\Domain\Book;
use App\Library\Domain\Category;
use App\Library\Domain\Guard;
use App\Library\Domain\User;
use App\Library\Domain\Errors\ForbiddenRole;
use App\Library\Domain\Errors\ResourceNotFound;
final class CatalogRules
{
    public static function admin(User $actor): void
    {
        if ($actor->role !== 'admin') { throw new ForbiddenRole; }
    }
    public static function book(BookRepository $books, string $id): Book
    {
        return $books->findActive(Guard::uuid($id, 'book_id'))
            ?? throw new ResourceNotFound('No se encontró el libro.');
    }
    public static function category(CategoryRepository $categories, string $id): Category
    {
        return $categories->find(Guard::uuid($id, 'category_id'))
            ?? throw new ResourceNotFound('No se encontró la categoría.');
    }
}
