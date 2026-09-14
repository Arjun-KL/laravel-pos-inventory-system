<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Product;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proves the lock actually blocks, not merely that `for update` appears in the
 * SQL. Two real connections are needed, so this class truncates between tests
 * instead of wrapping each one in a transaction — a second connection cannot
 * see rows written inside another connection's open transaction.
 *
 * Postgres error 55P03 is "lock not available": the probe connection asked for
 * a row lock, waited out its lock_timeout, and gave up. That wait is the
 * behaviour that stops the same unit being sold twice.
 */
final class StockLockTest extends TestCase
{
    use DatabaseTruncation;

    private const LOCK_NOT_AVAILABLE = '55P03';

    protected function tearDown(): void
    {
        // DatabaseTruncation cleans up before each of its own tests, not after.
        // The rows committed here would otherwise leak into every later test
        // class, which wraps itself in a transaction and still sees them.
        DB::statement('TRUNCATE products RESTART IDENTITY CASCADE');

        parent::tearDown();
    }

    public function test_a_locked_product_row_blocks_a_second_transaction(): void
    {
        $product = Product::factory()->withStock(5)->create();
        $probe = $this->probeConnection();

        DB::beginTransaction();

        try {
            Product::query()->whereKey($product->id)->lockForUpdate()->get();

            $error = $this->lockFromProbe($probe, $product->id);
        } finally {
            DB::rollBack();
            $probe->disconnect();
        }

        $this->assertNotNull($error, 'A second transaction locked a row that was already locked.');
        $this->assertStringContainsString(self::LOCK_NOT_AVAILABLE, $error);
    }

    public function test_locking_one_product_leaves_other_products_sellable(): void
    {
        $locked = Product::factory()->withStock(5)->create();
        $untouched = Product::factory()->withStock(5)->create();
        $probe = $this->probeConnection();

        DB::beginTransaction();

        try {
            Product::query()->whereKey($locked->id)->lockForUpdate()->get();

            $error = $this->lockFromProbe($probe, $untouched->id);
        } finally {
            DB::rollBack();
            $probe->disconnect();
        }

        // Row locks, not table locks. This is why Postgres is a hard
        // requirement: SQLite would have locked the whole database file.
        $this->assertNull($error, 'Locking one product must not block the sale of a different one.');
    }

    private function probeConnection(): Connection
    {
        config(['database.connections.pgsql_lock_probe' => config('database.connections.pgsql')]);

        $probe = DB::connection('pgsql_lock_probe');
        // Without this the probe would wait forever instead of failing the test.
        $probe->statement("SET lock_timeout = '500ms'");

        return $probe;
    }

    private function lockFromProbe(Connection $probe, int $productId): ?string
    {
        try {
            $probe->table('products')->where('id', $productId)->lockForUpdate()->get();

            return null;
        } catch (QueryException $exception) {
            return $exception->getMessage();
        }
    }
}
