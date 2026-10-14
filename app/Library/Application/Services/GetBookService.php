<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Domain\Book;
final readonly class GetBookService
{
    public function __construct(private BookRepository $books) {}
    /** La autenticación de lectura corresponde al adaptador HTTP, T-16. */
    public function execute(string $id): Book { return CatalogRules::book($this->books, $id); }
}
