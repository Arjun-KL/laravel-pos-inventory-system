<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockMovement extends Model
{
    public const REASON_ORDER_PLACED = 'order_placed';

    protected $fillable = [
        'product_id',
        'order_id',
        'reason',
        'quantity_change',
        'stock_after',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
