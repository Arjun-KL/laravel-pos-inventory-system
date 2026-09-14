<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PRD-####-??'),
            'name' => fake()->words(3, true),
            // Built as a string, not a float: the column is NUMERIC(12,2) and
            // the value should never take a trip through binary floating point.
            'price' => number_format(fake()->numberBetween(100, 500000) / 100, 2, '.', ''),
            'tax_percentage' => '18.00',
            'stock_on_hand' => fake()->numberBetween(10, 500),
        ];
    }

    public function outOfStock(): self
    {
        return $this->state(fn (): array => ['stock_on_hand' => 0]);
    }

    public function withStock(int $quantity): self
    {
        return $this->state(fn (): array => ['stock_on_hand' => $quantity]);
    }

    public function pricedAt(string $price, string $taxPercentage = '18.00'): self
    {
        return $this->state(fn (): array => [
            'price' => $price,
            'tax_percentage' => $taxPercentage,
        ]);
    }
}
