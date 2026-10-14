<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\ReportReader;
use App\Library\Domain\{DateRange, User};
final readonly class MostBorrowedReportService
{
    public function __construct(private ReportReader $reports) {}
    public function execute(User $actor, string $from, string $to): array
    {
        CatalogRules::admin($actor);
        $range = DateRange::fromDates($from, $to);
        return ['from' => $range->from->format('Y-m-d'), 'to' => $range->to->format('Y-m-d'),
            'rows' => $this->reports->mostBorrowed($range)];
    }
}
