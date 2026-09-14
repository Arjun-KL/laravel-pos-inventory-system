<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Product;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The brief's requirement 7, exactly: two orders for the last unit arrive at the
 * same time — one must succeed, the other must fail cleanly.
 *
 * Each order runs in its own PHP process with its own database connection, as two
 * web requests would. Merely starting two processes does not make them overlap —
 * one could finish before the other begins — so the test holds the product's row
 * lock itself, waits until PostgreSQL reports both orders queued behind that lock,
 * and only then releases it. From that instant the two orders genuinely race for
 * the same row.
 *
 * The child processes can only see committed data, so this class truncates
 * rather than wrapping each test in a transaction.
 */
final class ConcurrentOrdersTest extends TestCase
{
    use DatabaseTruncation;

    private const EXIT_PLACED = 0;

    private const EXIT_INSUFFICIENT_STOCK = 10;

    private const TIMEOUT_SECONDS = 30;

    /** @var list<Process> */
    private array $processes = [];

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0);
            }
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        // Committed rows would otherwise leak into later test classes, which wrap
        // themselves in a transaction and would still see them.
        DB::statement('TRUNCATE products, customers, orders, order_items, stock_movements, jobs RESTART IDENTITY CASCADE');

        parent::tearDown();
    }

    public function test_two_simultaneous_orders_for_the_last_unit_sell_it_exactly_once(): void
    {
        $product = Product::factory()->withStock(1)->create();
        $observer = $this->observerConnection();

        // Hold the row, so both orders are forced to queue behind the same lock.
        DB::beginTransaction();
        Product::query()->whereKey($product->id)->lockForUpdate()->get();

        $this->processes = [
            $this->startOrderProcess($product->id, 'first-buyer@example.com'),
            $this->startOrderProcess($product->id, 'second-buyer@example.com'),
        ];

        $this->waitUntilBothOrdersAreBlockedOnTheLock($observer);
        $observer->disconnect();

        // Let go. The two orders now race for the single unit.
        DB::rollBack();

        foreach ($this->processes as $process) {
            $process->wait();
        }

        $exitCodes = array_map(static fn (Process $process): ?int => $process->getExitCode(), $this->processes);
        sort($exitCodes);

        $this->assertSame(
            [self::EXIT_PLACED, self::EXIT_INSUFFICIENT_STOCK],
            $exitCodes,
            'Expected exactly one order placed and one rejected. '.$this->describeProcesses(),
        );

        $this->assertSame(0, $product->refresh()->stock_on_hand);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        // Only the order that succeeded queued a confirmation email.
        $this->assertDatabaseCount('jobs', 1);
    }

    private function startOrderProcess(int $productId, string $email): Process
    {
        $connection = config('database.connections.pgsql');

        $process = new Process(
            [PHP_BINARY, base_path('tests/Support/place-order.php'), (string) $productId, $email],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => (string) $connection['host'],
                'DB_PORT' => (string) $connection['port'],
                'DB_DATABASE' => (string) $connection['database'],
                'DB_USERNAME' => (string) $connection['username'],
                'DB_PASSWORD' => (string) $connection['password'],
                'QUEUE_CONNECTION' => 'database',
                'MAIL_MAILER' => 'array',
                'CACHE_STORE' => 'array',
            ],
        );

        $process->setTimeout(self::TIMEOUT_SECONDS * 2);
        $process->start();

        return $process;
    }

    /**
     * Polls pg_locks from a separate connection. A blocked row lock shows up as an
     * ungranted lock; two distinct waiting backends means both orders are queued.
     */
    private function waitUntilBothOrdersAreBlockedOnTheLock(Connection $observer): void
    {
        $deadline = microtime(true) + self::TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $waiting = (int) $observer->scalar(<<<'SQL'
                SELECT count(DISTINCT l.pid)
                FROM pg_locks l
                JOIN pg_stat_activity a ON a.pid = l.pid
                WHERE NOT l.granted AND a.datname = current_database()
                SQL);

            if ($waiting >= 2) {
                return;
            }

            foreach ($this->processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('An order process exited before reaching the lock. '.$this->describeProcesses());
                }
            }

            usleep(50_000);
        }

        $this->fail('Timed out waiting for both orders to queue behind the lock. '.$this->describeProcesses());
    }

    private function observerConnection(): Connection
    {
        config(['database.connections.pgsql_observer' => config('database.connections.pgsql')]);

        return DB::connection('pgsql_observer');
    }

    private function describeProcesses(): string
    {
        return implode(' | ', array_map(
            static fn (Process $process): string => sprintf(
                'exit=%s stdout=%s stderr=%s',
                var_export($process->getExitCode(), true),
                trim($process->getOutput()),
                trim($process->getErrorOutput()),
            ),
            $this->processes,
        ));
    }
}
