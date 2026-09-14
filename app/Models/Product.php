<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'price',
        'tax_percentage',
        'stock_on_hand',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // decimal:2 returns strings on purpose — they preserve the exact
            // stored value. Never do arithmetic on these directly; use Money.
            'price' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'stock_on_hand' => 'integer',
        ];
    }

    public function hasStockFor(int $quantity): bool
    {
        return $this->stock_on_hand >= $quantity;
    }

    /**
     * Strictly below: with a threshold of 10, a product with exactly 10 units
     * is not yet low.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeBelowStock(Builder $query, int $threshold): void
    {
        $query->where('stock_on_hand', '<', $threshold);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
