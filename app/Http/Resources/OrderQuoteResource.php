<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\OrderQuote;
use App\Data\PricedLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property OrderQuote $resource */
final class OrderQuoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $quote = $this->resource;
        $priced = $quote->pricedOrder;

        return [
            'lines' => array_map(static fn (PricedLine $line): array => [
                'product_id' => $line->productId,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'tax_percentage' => $line->taxPercentage,
                'line_subtotal' => $line->lineSubtotal,
                'line_tax' => $line->lineTax,
                'line_total' => $line->lineTotal,
                'stock_on_hand' => $quote->stockOnHand[$line->productId],
                'is_available' => $quote->isAvailable($line->productId),
            ], $priced->lines),
            'subtotal' => $priced->subtotal,
            'tax_total' => $priced->taxTotal,
            'total' => $priced->total,
            'amount_paid' => $quote->amountPaid,
            'change_due' => $quote->changeDue,
        ];
    }
}
