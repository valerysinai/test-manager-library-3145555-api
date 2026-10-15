<?php
declare(strict_types=1);
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

final class PaginationBoundaryTest extends ApiCase
{
    public static function collections(): array
    {
        return [['/books'], ['/members'], ['/loans']];
    }

    #[DataProvider('collections')]
    public function test_page_beyond_integer_offset_returns_empty_items_and_real_total(string $path): void
    {
        $token = $this->login();
        $book = $this->book($token);
        $member = $this->member($token);
        $this->lend($token, $member['id'], [$book['id']])->assertCreated();

        $this->api('GET', $path.'?page='.PHP_INT_MAX.'&size=50', null, $token)
            ->assertOk()->assertExactJson(['items'=>[], 'page'=>PHP_INT_MAX, 'size'=>50, 'total'=>1]);
        $this->api('GET', $path.'?page=2&size=1', null, $token)
            ->assertOk()->assertExactJson(['items'=>[], 'page'=>2, 'size'=>1, 'total'=>1]);
        $this->api('GET', $path.'?page=1&size=1', null, $token)
            ->assertOk()->assertJsonPath('total', 1)->assertJsonCount(1, 'items');
    }

    #[DataProvider('collections')]
    public function test_empty_collection_preserves_large_page_without_invalid_offset(string $path): void
    {
        $token = $this->login();
        $this->api('GET', $path.'?page='.PHP_INT_MAX.'&size=50', null, $token)
            ->assertOk()->assertExactJson(['items'=>[], 'page'=>PHP_INT_MAX, 'size'=>50, 'total'=>0]);
    }
}
