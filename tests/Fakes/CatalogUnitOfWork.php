<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\Errors\ConcurrentModification;
use Closure;
use Throwable;
final readonly class CatalogUnitOfWork implements UnitOfWork
{
    public function __construct(private InMemoryCatalog $catalog) {}
    public function run(Closure $operation): mixed
    {
        if ($this->catalog->inTransaction) { throw new \LogicException('Transacción anidada inesperada.'); }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $snapshot = $this->catalog->books;
            $this->catalog->inTransaction = true;
            $this->catalog->events[] = 'begin';
            try {
                $result = $operation();
                $this->catalog->inTransaction = false;
                $this->catalog->events[] = 'commit';
                return $result;
            } catch (Throwable $failure) {
                $this->catalog->books = $snapshot;
                $this->catalog->inTransaction = false;
                $this->catalog->events[] = 'rollback';
                if (!$failure instanceof ConcurrentModification || $attempt === 1) { throw $failure; }
                if ($this->catalog->afterConflict !== null) {
                    ($this->catalog->afterConflict)();
                }
            }
        }
        throw new \LogicException('Recorrido de reintento inválido.');
    }
}
