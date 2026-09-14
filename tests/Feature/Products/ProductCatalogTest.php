<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_products_with_price_tax_and_stock(): void
    {
        Product::factory()->pricedAt('49.99', '5.00')->withStock(7)->create([
            'code' => 'MUG-01',
            'name' => 'Coffee Mug',
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'MUG-01')
            ->assertJsonPath('data.0.name', 'Coffee Mug')
            ->assertJsonPath('data.0.price', '49.99')
            ->assertJsonPath('data.0.tax_percentage', '5.00')
            ->assertJsonPath('data.0.stock_on_hand', 7);
    }

    public function test_the_catalogue_is_paginated(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->getJson('/api/products?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }
}
