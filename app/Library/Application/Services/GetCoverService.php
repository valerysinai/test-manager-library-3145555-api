<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\CoverContent;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\CoverStorage;
use App\Library\Domain\Errors\ResourceNotFound;
final readonly class GetCoverService
{
    public function __construct(private BookRepository $books, private CoverStorage $covers) {}
    public function execute(string $id): CoverContent
    {
        $book = CatalogRules::book($this->books, $id);
        return ($book->coverKey === null ? null : $this->covers->read($book->coverKey))
            ?? throw new ResourceNotFound('No se encontró la portada.');
    }
}
