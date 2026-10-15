<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\ReportReader;
use App\Library\Domain\DateRange;
final readonly class PgReportReader implements ReportReader
{
    public function __construct(private PgSession $session) {}
    public function mostBorrowed(DateRange $range): array
    {
        $rows = $this->session->all('SELECT i.book_id,i.book_title,i.category_name,count(*)::integer AS times_borrowed
            FROM library.loan_item i JOIN library.loan l ON l.id=i.loan_id
            WHERE l.loaned_at >= :from AND l.loaned_at < :to
            GROUP BY i.book_id,i.book_title,i.category_name
            ORDER BY times_borrowed DESC,i.book_title,i.book_id,i.category_name',
            ['from' => PgSession::date($range->from), 'to' => PgSession::date($range->toExclusive())]);
        foreach ($rows as &$row) { $row['times_borrowed'] = (int) $row['times_borrowed']; }
        return $rows;
    }
}
