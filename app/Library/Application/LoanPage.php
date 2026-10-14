<?php
declare(strict_types=1);
namespace App\Library\Application;
use App\Library\Domain\Loan;
final readonly class LoanPage
{
    public function __construct(public array $items, public int $page, public int $size, public int $total)
    {
        if (!array_is_list($items) || $page < 1 || $size < 1 || $size > 50 || count($items) > $size || $total < count($items)) {
            throw new \InvalidArgumentException('Página de préstamos inválida.');
        }
        foreach ($items as $loan) { if (!$loan instanceof Loan) { throw new \InvalidArgumentException('Préstamo inválido.'); } }
    }
}
