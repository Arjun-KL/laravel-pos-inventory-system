<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'tax_percentage',
        'line_subtotal',
        'line_tax',
        'line_total',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            // Snapshotted from the product at the moment of sale — see the
            // migration for why this duplication is deliberate.
            'unit_price' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
