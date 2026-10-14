<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\Errors\ConcurrentModification;
use Closure;
use PDOException;
use Throwable;
final readonly class PgUnitOfWork implements UnitOfWork
{
    public function __construct(private PgSession $session) {}
    public function run(Closure $operation): mixed
    {
        $pdo = $this->session->pdo();
        if ($pdo->inTransaction()) { throw new \LogicException('No se admite transacción anidada.'); }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->session->versions = [];
            $pdo->beginTransaction();
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            try {
                $result = $operation();
                $pdo->commit();
                $this->session->versions = [];
                return $result;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $this->session->versions = [];
                $retry = $error instanceof ConcurrentModification
                    || ($error instanceof PDOException && in_array($error->getCode(), ['40001', '40P01'], true));
                if ($retry && $attempt === 0) { continue; }
                if ($retry && !$error instanceof ConcurrentModification) { throw new ConcurrentModification; }
                throw $error;
            }
        }
        throw new ConcurrentModification;
    }
}
