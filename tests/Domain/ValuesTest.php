<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Library\Domain\DateRange;
use App\Library\Domain\DocumentNumber;
use App\Library\Domain\Errors\InvalidDateRange;
use App\Library\Domain\Errors\ValidationError;
use App\Library\Domain\Isbn;
use App\Library\Domain\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValuesTest extends TestCase
{
    public function test_isbn_has_only_the_documented_digit_rule_not_a_checksum_rule(): void
    {
        self::assertSame('0000000000000', (new Isbn('0000000000000'))->value);
        self::assertSame('9780307474728', (new Isbn('9780307474728'))->value);
    }

    #[DataProvider('invalidIsbn')]
    public function test_invalid_isbn_is_rejected(string $value): void
    {
        $this->expectException(ValidationError::class);
        new Isbn($value);
    }

    public static function invalidIsbn(): array
    {
        return [[''], ['123'], ['12345678901234'], ['978-0307474728'], ['978030747472a'], ['9780307474728 '], ['１２３４５６７８９０１２３']];
    }

    #[DataProvider('invalidQuantity')]
    public function test_quantities_must_fit_the_documented_positive_integer(string $value): void
    {
        $this->expectException(ValidationError::class);
        new Quantity((int) $value);
    }

    public static function invalidQuantity(): array
    {
        return [['0'], ['-1'], ['2147483648']];
    }

    public function test_quantity_and_document_values_are_normalized_without_extra_format_rules(): void
    {
        self::assertSame(1, (new Quantity(1))->value);
        self::assertSame('CC-001', (new DocumentNumber(' CC-001 '))->value);
    }

    #[DataProvider('invalidDocument')]
    public function test_document_is_required_and_fits_its_column(string $value): void
    {
        $this->expectException(ValidationError::class);
        new DocumentNumber($value);
    }

    public static function invalidDocument(): array
    {
        return [['  '], [str_repeat('a', 31)]];
    }

    public function test_date_range_includes_the_entire_end_date_in_utc(): void
    {
        $range = DateRange::fromDates('2026-10-14', '2026-10-14');
        self::assertSame('UTC', $range->from->getTimezone()->getName());
        self::assertTrue($range->contains(new DateTimeImmutable('2026-10-14T23:59:59.999999Z')));
        self::assertFalse($range->contains(new DateTimeImmutable('2026-10-15T00:00:00Z')));
        self::assertTrue($range->contains(new DateTimeImmutable('2026-10-13T19:00:00-05:00')));
        self::assertFalse($range->contains(new DateTimeImmutable('2026-10-13T23:59:59Z')));
    }

    public function test_inverted_range_has_its_own_business_error(): void
    {
        $this->expectException(InvalidDateRange::class);
        DateRange::fromDates('2026-10-15', '2026-10-14');
    }

    #[DataProvider('invalidDate')]
    public function test_invalid_calendar_dates_are_not_silently_normalized(string $value): void
    {
        $this->expectException(ValidationError::class);
        DateRange::fromDates($value, '2026-12-31');
    }

    public static function invalidDate(): array
    {
        return [['2026-02-30'], ['2026-2-01'], ['2026-10-14T12:00:00Z'], [''], [' 2026-10-14']];
    }

    #[DataProvider('nullByteEndpoints')]
    public function test_null_byte_dates_raise_a_field_validation_error_instead_of_a_native_error(string $from, string $to, string $field): void
    {
        try {
            DateRange::fromDates($from, $to);
            self::fail('Debió rechazar la fecha con byte nulo.');
        } catch (ValidationError $error) {
            self::assertSame($field, $error->field);
        }
    }

    public static function nullByteEndpoints(): array
    {
        $invalid = '2026-10-14'.chr(0);

        return [[$invalid, '2026-10-15', 'from'], ['2026-10-13', $invalid, 'to']];
    }
}
