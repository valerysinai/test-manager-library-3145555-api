<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\Errors\ConcurrentModification;
use Closure;
final readonly class LoanUnitOfWork implements UnitOfWork
{
    public function __construct(private InMemoryCatalog $catalog,private InMemoryLoans $loans) {}
    public function run(Closure $operation): mixed
    {
        for($attempt=0;$attempt<2;$attempt++) {
            $books=$this->catalog->books; $loans=$this->loans->loans; $this->catalog->inTransaction=true;
            try { $result=$operation(); $this->catalog->inTransaction=false; return $result; }
            catch(\Throwable $e) {
                $this->catalog->books=$books; $this->loans->loans=$loans; $this->catalog->inTransaction=false;
                if($e instanceof ConcurrentModification && $attempt===0) continue;
                throw $e;
            }
        }
        throw new ConcurrentModification;
    }
}
