<?php
declare(strict_types=1);
namespace App\Library\Application;
use App\Library\Domain\Book;
final readonly class BookPage
{
    /** @param list<Book> $items */
    public function __construct(public array $items, public int $page, public int $size, public int $total)
    {
        if (!array_is_list($items) || $page < 1 || $size < 1 || $size > 50
            || count($items) > $size || $total < count($items)) {
            throw new \InvalidArgumentException('La página de libros no cumple el puerto.');
        }
        foreach ($items as $book) {
            if (!$book instanceof Book || $book->deletedAt !== null) {
                throw new \InvalidArgumentException('El catálogo debe contener libros activos.');
            }
        }
    }
}
