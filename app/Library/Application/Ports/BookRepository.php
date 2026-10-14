<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Application\BookPage;
use App\Library\Domain\Book;
use App\Library\Domain\Isbn;
interface BookRepository
{
    /** Filtro activo centralizado también en búsquedas e ISBN; histórico no usa este puerto. */
    public function findActive(string $id): ?Book;
    public function findActiveByIsbn(Isbn $isbn): ?Book;
    /** Traducir solo el índice ISBN activo a IsbnTaken, también ante carrera de inserción. */
    public function add(Book $book): void;
    /** Dentro de UnitOfWork; el adaptador compara el testigo que leyó en este intento. */
    public function save(Book $book): void;
    public function hasOpenLoans(string $bookId): bool;
    public function search(string $query, ?string $categoryId, int $page, int $size): BookPage;
}
