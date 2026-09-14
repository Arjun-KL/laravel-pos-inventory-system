<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
final class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            // Charged values come from the line itself (snapshotted at sale).
            'unit_price' => $this->unit_price,
            'tax_percentage' => $this->tax_percentage,
            'line_subtotal' => $this->line_subtotal,
            'line_tax' => $this->line_tax,
            'line_total' => $this->line_total,
            // Descriptive values come from the product, live. Its current price
            // and stock are left out so they can't be mistaken for what was charged.
            'product' => $this->whenLoaded('product', fn (): array => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
        ];
    }
}
