<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\CoverStorage;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\User;
final readonly class RemoveCoverService
{
    public function __construct(private BookRepository $books, private CoverStorage $covers, private UnitOfWork $work) {}
    public function execute(User $actor, string $id): void
    {
        CatalogRules::admin($actor);
        $key = $this->work->run(function () use ($id): ?string {
            $book = CatalogRules::book($this->books, $id);
            if ($book->coverKey !== null) { $this->books->save($book->withCover(null)); }
            return $book->coverKey;
        });
        if ($key !== null) { $this->covers->delete($key); }
    }
}
