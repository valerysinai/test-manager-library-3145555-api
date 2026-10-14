<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\LoanRepository;
use App\Library\Domain\{Guard, Loan};
use App\Library\Domain\Errors\ResourceNotFound;
final readonly class GetLoanService
{
    public function __construct(private LoanRepository $loans) {}
    public function execute(string $id): Loan
    {
        return $this->loans->find(Guard::uuid($id, 'loan_id')) ?? throw new ResourceNotFound('No se encontró el préstamo.');
    }
}
