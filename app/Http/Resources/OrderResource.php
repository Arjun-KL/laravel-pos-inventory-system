<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'change_due' => $this->change_due,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
