<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_a_catalogue_customers_and_some_low_stock_items(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, Product::query()->count());
        $this->assertGreaterThan(0, Customer::query()->count());
        $this->assertGreaterThan(
            0,
            Product::query()->belowStock((int) config('inventory.low_stock_threshold'))->count(),
            'The seeded catalogue should give the low-stock alert something to show.',
        );

        // Opening stock is not a sale: no orders or ledger rows are invented.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
