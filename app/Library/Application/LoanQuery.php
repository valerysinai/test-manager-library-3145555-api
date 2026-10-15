<?php
declare(strict_types=1);
namespace App\Library\Application;
use App\Library\Domain\DateRange;
use App\Library\Domain\Guard;
use App\Library\Domain\Errors\InvalidDateRange;
use App\Library\Domain\Errors\ValidationError;
use DateTimeImmutable;
final readonly class LoanQuery
{
    public ?DateTimeImmutable $from;
    public ?DateTimeImmutable $toExclusive;
    public ?string $memberId;
    public function __construct(?string $from, ?string $to, public ?string $status, ?string $memberId,
        public int $page = 1, public int $size = 20)
    {
        $this->from = $from === null ? null : DateRange::fromDates($from, $from)->from;
        $this->toExclusive = $to === null ? null : DateRange::fromDates($to, $to)->toExclusive();
        if ($this->from !== null && $this->toExclusive !== null && $this->from >= $this->toExclusive) {
            throw new InvalidDateRange('El fin del rango no puede ser anterior al inicio.');
        }
        if ($status !== null && !in_array($status, ['open', 'overdue', 'closed'], true)) {
            throw new ValidationError('status', 'El estado no es válido.');
        }
        if ($page < 1 || $size < 1 || $size > 50) { throw new ValidationError('page/size', 'Paginación inválida.'); }
        $this->memberId = $memberId === null ? null : Guard::uuid($memberId, 'member_id');
    }
}
