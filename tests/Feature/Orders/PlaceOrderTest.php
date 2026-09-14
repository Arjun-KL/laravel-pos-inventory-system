<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_it_places_an_order_deducts_stock_and_writes_the_ledger(): void
    {
        $shirt = Product::factory()->pricedAt('100.00', '18.00')->withStock(10)->create();
        $mug = Product::factory()->pricedAt('49.99', '5.00')->withStock(3)->create();

        $response = $this->placeOrder([
            ['product_id' => $shirt->id, 'quantity' => 2],
            ['product_id' => $mug->id, 'quantity' => 3],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', Order::STATUS_PLACED)
            ->assertJsonPath('data.subtotal', '349.97')
            ->assertJsonPath('data.tax_total', '43.50')
            ->assertJsonPath('data.total', '393.47')
            ->assertJsonPath('data.amount_paid', null)
            ->assertJsonPath('data.change_due', null)
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(8, $shirt->refresh()->stock_on_hand);
        $this->assertSame(0, $mug->refresh()->stock_on_hand);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $shirt->id,
            'quantity' => 2,
            'unit_price' => '100.00',
            'line_subtotal' => '200.00',
            'line_tax' => '36.00',
            'line_total' => '236.00',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $mug->id,
            'reason' => StockMovement::REASON_ORDER_PLACED,
            'quantity_change' => -3,
            'stock_after' => 0,
        ]);

        Queue::assertPushed(SendOrderConfirmation::class, 1);
    }

    public function test_when_one_unit_is_left_the_first_order_succeeds_and_the_second_fails_cleanly(): void
    {
        // The brief's scenario, played in sequence. What makes it hold when the
        // two requests genuinely overlap is the row lock — proven with two real
        // connections in StockLockTest, and end to end by
        // scripts/concurrency-check.sh.
        $product = Product::factory()->withStock(1)->create();
        $line = [['product_id' => $product->id, 'quantity' => 1]];

        $this->placeOrder($line, ['name' => 'First Buyer', 'email' => 'first@example.com'])
            ->assertCreated();

        $this->placeOrder($line, ['name' => 'Second Buyer', 'email' => 'second@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity']);

        $this->assertSame(0, $product->refresh()->stock_on_hand);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        Queue::assertPushed(SendOrderConfirmation::class, 1);
    }

    public function test_tax_is_rounded_per_line_so_every_line_adds_up(): void
    {
        // 149.97 at 5% is 7.4985 — rounding once on the order total instead of
        // per line would leave line_subtotal + line_tax != line_total.
        $mug = Product::factory()->pricedAt('49.99', '5.00')->withStock(10)->create();

        $this->placeOrder([['product_id' => $mug->id, 'quantity' => 3]])->assertCreated();

        $item = OrderItem::query()->sole();

        $this->assertSame('149.97', $item->line_subtotal);
        $this->assertSame('7.50', $item->line_tax);
        $this->assertSame('157.47', $item->line_total);
    }

    public function test_a_failed_order_leaves_no_trace_at_all(): void
    {
        $available = Product::factory()->withStock(10)->create();
        $short = Product::factory()->withStock(1)->create();

        $response = $this->placeOrder([
            ['product_id' => $available->id, 'quantity' => 2],
            ['product_id' => $short->id, 'quantity' => 3],
        ]);

        $response->assertUnprocessable();

        // The line that would have fitted must not be half-applied.
        $this->assertSame(10, $available->refresh()->stock_on_hand);
        $this->assertSame(1, $short->refresh()->stock_on_hand);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        Queue::assertNothingPushed();
    }

    public function test_it_reports_every_short_line_in_one_response(): void
    {
        $first = Product::factory()->withStock(1)->create();
        $second = Product::factory()->outOfStock()->create();
        $fine = Product::factory()->withStock(50)->create();

        $response = $this->placeOrder([
            ['product_id' => $first->id, 'quantity' => 5],
            ['product_id' => $fine->id, 'quantity' => 1],
            ['product_id' => $second->id, 'quantity' => 2],
        ]);

        // One round trip, every problem: not just the first short line.
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity', 'lines.2.quantity'])
            ->assertJsonMissingValidationErrors(['lines.1.quantity']);
    }

    public function test_ordering_exactly_the_remaining_stock_succeeds(): void
    {
        $product = Product::factory()->withStock(3)->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 3]])->assertCreated();

        $this->assertSame(0, $product->refresh()->stock_on_hand);
    }

    public function test_ordering_one_more_than_the_remaining_stock_fails(): void
    {
        $product = Product::factory()->withStock(3)->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 4]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity']);

        $this->assertSame(3, $product->refresh()->stock_on_hand);
    }

    public function test_repeated_lines_for_the_same_product_are_counted_together_against_stock(): void
    {
        $product = Product::factory()->withStock(10)->create();

        // Six and six is twelve, which does not fit — even though neither line
        // exceeds the stock on its own.
        $this->placeOrder([
            ['product_id' => $product->id, 'quantity' => 6],
            ['product_id' => $product->id, 'quantity' => 6],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity', 'lines.1.quantity']);

        $this->assertSame(10, $product->refresh()->stock_on_hand);
    }

    public function test_repeated_lines_for_the_same_product_deduct_once_in_total(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->placeOrder([
            ['product_id' => $product->id, 'quantity' => 4],
            ['product_id' => $product->id, 'quantity' => 6],
        ])->assertCreated();

        $this->assertSame(0, $product->refresh()->stock_on_hand);
        $this->assertDatabaseCount('order_items', 2);
        // One movement for the product, carrying the combined change.
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => -10,
            'stock_after' => 0,
        ]);
    }

    public function test_it_records_the_amount_paid_and_the_change_due(): void
    {
        $product = Product::factory()->pricedAt('100.00', '18.00')->withStock(5)->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 2]], null, ['amount_paid' => '250'])
            ->assertCreated()
            ->assertJsonPath('data.total', '236.00')
            ->assertJsonPath('data.amount_paid', '250.00')
            ->assertJsonPath('data.change_due', '14.00');
    }

    public function test_paying_the_exact_total_leaves_no_change(): void
    {
        $product = Product::factory()->pricedAt('100.00', '18.00')->withStock(5)->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 2]], null, ['amount_paid' => '236.00'])
            ->assertCreated()
            ->assertJsonPath('data.change_due', '0.00');
    }

    public function test_an_order_paid_short_is_rejected_and_leaves_no_trace(): void
    {
        $product = Product::factory()->pricedAt('100.00', '18.00')->withStock(5)->create();

        // One paisa short of 236.00.
        $this->placeOrder([['product_id' => $product->id, 'quantity' => 2]], null, ['amount_paid' => '235.99'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_paid']);

        $this->assertSame(5, $product->refresh()->stock_on_hand);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        Queue::assertNothingPushed();
    }

    public function test_it_rejects_an_amount_paid_with_more_than_two_decimal_places(): void
    {
        $product = Product::factory()->withStock(5)->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]], null, ['amount_paid' => '1000.005'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_paid']);
    }

    public function test_an_order_line_keeps_the_price_it_was_sold_at(): void
    {
        $product = Product::factory()->pricedAt('100.00', '18.00')->withStock(5)->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]])->assertCreated();

        $product->update(['price' => '250.00', 'tax_percentage' => '12.00', 'name' => 'Renamed product']);

        $item = OrderItem::query()->with('product')->sole();

        // What was charged is frozen on the line...
        $this->assertSame('100.00', $item->unit_price);
        $this->assertSame('18.00', $item->tax_percentage);
        $this->assertSame('118.00', $item->line_total);
        // ...while descriptive data is read live through the relationship.
        $this->assertSame('Renamed product', $item->product->name);
    }

    public function test_stock_is_selected_for_update_in_ascending_id_order(): void
    {
        $first = Product::factory()->withStock(5)->create();
        $second = Product::factory()->withStock(5)->create();

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        // Requested highest id first: the action must still lock ascending, or
        // two orders touching the same pair of products can deadlock.
        $this->placeOrder([
            ['product_id' => $second->id, 'quantity' => 1],
            ['product_id' => $first->id, 'quantity' => 1],
        ])->assertCreated();

        $lockingSelects = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'from "products"') && str_contains($sql, 'for update'),
        ));

        $this->assertCount(1, $lockingSelects, 'Products must be locked exactly once, in a single query.');
        $this->assertStringContainsString('order by "id" asc', $lockingSelects[0]);
    }

    public function test_it_rejects_a_line_for_a_product_that_does_not_exist(): void
    {
        $this->placeOrder([['product_id' => 987654, 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.product_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_rejects_a_quantity_below_one(): void
    {
        $product = Product::factory()->withStock(5)->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 0]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity']);

        $this->assertSame(5, $product->refresh()->stock_on_hand);
    }

    public function test_it_rejects_an_order_with_no_lines(): void
    {
        $this->placeOrder([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_a_returning_customer_is_matched_on_email_regardless_of_case(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $lines = [['product_id' => $product->id, 'quantity' => 1]];

        $this->placeOrder($lines, ['name' => 'Asha Rao', 'email' => 'Asha@Example.COM'])->assertCreated();
        $this->placeOrder($lines, ['name' => 'Asha Rao', 'email' => 'asha@example.com'])->assertCreated();

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame('asha@example.com', Customer::query()->sole()->email);
        $this->assertSame(2, Order::query()->count());
    }

    public function test_an_existing_customer_keeps_the_name_on_file(): void
    {
        $product = Product::factory()->withStock(10)->create();
        Customer::factory()->create(['name' => 'Asha Rao', 'email' => 'asha@example.com']);

        // Email is the identity; a different name typed at the counter must not
        // rename the customer.
        $this->placeOrder(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Someone Else', 'email' => 'ASHA@example.com'],
        )
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Asha Rao');

        $this->assertDatabaseCount('customers', 1);
    }

    /**
     * @param  array<int, array<string, int>>  $lines
     * @param  array<string, string>|null  $customer
     * @param  array<string, mixed>  $extra
     */
    private function placeOrder(array $lines, ?array $customer = null, array $extra = []): TestResponse
    {
        return $this->postJson('/api/orders', [
            'customer' => $customer ?? ['name' => 'Asha Rao', 'email' => 'asha@example.com'],
            'lines' => $lines,
            ...$extra,
        ]);
    }
}
