<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{Clock, LoanRepository};
final readonly class OverdueService
{
    public function __construct(private LoanRepository $loans, private Clock $clock) {}
    public function execute(): array { return $this->loans->overdue($this->clock->now()); }
}
