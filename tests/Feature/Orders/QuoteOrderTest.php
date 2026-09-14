<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class QuoteOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_a_quote_prices_the_lines_without_writing_anything(): void
    {
        $shirt = Product::factory()->pricedAt('100.00', '18.00')->withStock(10)->create();
        $mug = Product::factory()->pricedAt('49.99', '5.00')->withStock(3)->create();

        $this->postJson('/api/orders/quote', [
            'lines' => [
                ['product_id' => $shirt->id, 'quantity' => 2],
                ['product_id' => $mug->id, 'quantity' => 3],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '349.97')
            ->assertJsonPath('data.tax_total', '43.50')
            ->assertJsonPath('data.total', '393.47')
            ->assertJsonPath('data.lines.1.line_tax', '7.50')
            ->assertJsonPath('data.amount_paid', null)
            ->assertJsonPath('data.change_due', null);

        // A quote reserves nothing.
        $this->assertSame(10, $shirt->refresh()->stock_on_hand);
        $this->assertSame(3, $mug->refresh()->stock_on_hand);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        Queue::assertNothingPushed();
    }

    public function test_a_quote_flags_lines_that_exceed_stock_instead_of_rejecting_them(): void
    {
        $product = Product::factory()->withStock(2)->create();

        $this->postJson('/api/orders/quote', ['lines' => [['product_id' => $product->id, 'quantity' => 5]]])
            ->assertOk()
            ->assertJsonPath('data.lines.0.is_available', false)
            ->assertJsonPath('data.lines.0.stock_on_hand', 2);
    }

    public function test_repeated_lines_are_counted_together_when_flagging_stock(): void
    {
        $product = Product::factory()->withStock(3)->create();

        $this->postJson('/api/orders/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.is_available', false)
            ->assertJsonPath('data.lines.1.is_available', false);
    }

    public function test_a_quote_shows_the_change_due_or_the_amount_still_owed(): void
    {
        $product = Product::factory()->pricedAt('100.00', '18.00')->withStock(5)->create();
        $lines = [['product_id' => $product->id, 'quantity' => 1]];

        $this->postJson('/api/orders/quote', ['lines' => $lines, 'amount_paid' => '120'])
            ->assertOk()
            ->assertJsonPath('data.total', '118.00')
            ->assertJsonPath('data.amount_paid', '120.00')
            ->assertJsonPath('data.change_due', '2.00');

        $this->postJson('/api/orders/quote', ['lines' => $lines, 'amount_paid' => '100'])
            ->assertOk()
            ->assertJsonPath('data.change_due', '-18.00');
    }

    public function test_a_quote_rejects_a_product_that_does_not_exist(): void
    {
        $this->postJson('/api/orders/quote', ['lines' => [['product_id' => 987654, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.product_id']);
    }
}
