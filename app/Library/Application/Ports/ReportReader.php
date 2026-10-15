<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\DateRange;
interface ReportReader
{
    /** Una consulta agregada por book_id, book_title y category_name congelados; sin datos personales. */
    public function mostBorrowed(DateRange $range): array;
}
