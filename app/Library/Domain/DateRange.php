<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\InvalidDateRange;
use App\Library\Domain\Errors\ValidationError;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class DateRange
{
    private function __construct(public DateTimeImmutable $from, public DateTimeImmutable $to)
    {
        if ($to < $from) {
            throw new InvalidDateRange('El fin del rango no puede ser anterior al inicio.');
        }
    }

    public static function fromDates(string $from, string $to): self
    {
        return new self(self::date($from, 'from'), self::date($to, 'to'));
    }

    public function toExclusive(): DateTimeImmutable
    {
        return $this->to->add(new DateInterval('P1D'));
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $instant >= $this->from && $instant < $this->toExclusive();
    }

    private static function date(string $value, string $field): DateTimeImmutable
    {
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            throw new ValidationError($field, 'La fecha debe ser válida y usar el formato YYYY-MM-DD.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ValidationError($field, 'La fecha debe ser válida y usar el formato YYYY-MM-DD.');
        }

        return $date;
    }
}
