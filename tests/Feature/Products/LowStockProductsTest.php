<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LowStockProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_products_strictly_below_the_configured_threshold(): void
    {
        config(['inventory.low_stock_threshold' => 5]);

        $four = Product::factory()->withStock(4)->create();
        $empty = Product::factory()->outOfStock()->create();
        Product::factory()->withStock(5)->create();
        Product::factory()->withStock(50)->create();

        $response = $this->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonPath('meta.threshold', 5);

        // Exactly at the threshold is not "below" it.
        $this->assertEqualsCanonicalizing([$four->id, $empty->id], array_column($response->json('data'), 'id'));
    }

    public function test_the_threshold_can_be_overridden_per_request(): void
    {
        config(['inventory.low_stock_threshold' => 5]);

        $seven = Product::factory()->withStock(7)->create();
        Product::factory()->withStock(12)->create();

        $response = $this->getJson('/api/products/low-stock?threshold=10')
            ->assertOk()
            ->assertJsonPath('meta.threshold', 10);

        $this->assertSame([$seven->id], array_column($response->json('data'), 'id'));
    }

    public function test_an_empty_threshold_falls_back_to_the_configured_value_rather_than_zero(): void
    {
        config(['inventory.low_stock_threshold' => 5]);
        Product::factory()->withStock(1)->create();

        $this->getJson('/api/products/low-stock?threshold=')
            ->assertOk()
            ->assertJsonPath('meta.threshold', 5)
            ->assertJsonCount(1, 'data');
    }

    public function test_the_lowest_stock_is_listed_first(): void
    {
        $three = Product::factory()->withStock(3)->create();
        $zero = Product::factory()->outOfStock()->create();
        $one = Product::factory()->withStock(1)->create();

        $response = $this->getJson('/api/products/low-stock?threshold=10')->assertOk();

        $this->assertSame([$zero->id, $one->id, $three->id], array_column($response->json('data'), 'id'));
    }

    public function test_it_rejects_a_negative_threshold(): void
    {
        $this->getJson('/api/products/low-stock?threshold=-1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['threshold']);
    }
}
