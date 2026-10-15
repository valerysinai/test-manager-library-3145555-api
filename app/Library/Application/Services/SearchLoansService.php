<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\{LoanPage, LoanQuery};
use App\Library\Application\Ports\{Clock, LoanRepository};
final readonly class SearchLoansService
{
    public function __construct(private LoanRepository $loans, private Clock $clock) {}
    public function execute(LoanQuery $query): LoanPage { return $this->loans->search($query, $this->clock->now()); }
}
