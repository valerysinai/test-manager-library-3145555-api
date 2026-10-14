<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\Clock;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\User;
final readonly class DeactivateBookService
{
    public function __construct(private BookRepository $books, private UnitOfWork $work, private Clock $clock) {}
    public function execute(User $actor, string $id): void
    {
        CatalogRules::admin($actor);
        $this->work->run(function () use ($id): void {
            $book = CatalogRules::book($this->books, $id);
            $this->books->save($book->deactivate($this->clock->now(), $this->books->hasOpenLoans($book->id)));
        });
    }
}
