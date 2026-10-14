<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Application\LoanPage;
use App\Library\Application\LoanQuery;
use App\Library\Domain\Loan;
use App\Library\Domain\LoanItem;
use DateTimeImmutable;
interface LoanRepository
{
    /** Coordinación del socio en la unidad de trabajo, antes de leer su situación. */
    public function lockMember(string $memberId): void;
    public function memberId(string $loanId): ?string;
    public function find(string $id): ?Loan;
    /** @return list<Loan> */
    public function forMember(string $memberId): array;
    public function add(Loan $loan): void;
    /** Transición condicional; cero filas se traduce a ItemAlreadyReturned. */
    public function markReturned(LoanItem $item): void;
    public function search(LoanQuery $query, DateTimeImmutable $now): LoanPage;
    /** @return list<array{loan_id:string,item_id:string,book_title:string,member_name:string,due_at:string,days_late:int}> */
    public function overdue(DateTimeImmutable $now): array;
}
