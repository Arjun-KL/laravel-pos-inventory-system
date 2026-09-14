<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Data\OrderLineData;
use App\Data\OrderQuote;
use App\Exceptions\UnknownProductException;
use App\Models\Product;
use App\Support\Money;
use App\Support\OrderPricing;
use InvalidArgumentException;

/**
 * Prices a prospective order for the billing screen without placing it.
 *
 * No lock and no transaction: a quote reserves nothing and writes nothing. Its
 * stock figures are advisory, and are stale the moment they are read — the
 * binding check and the final price both happen under lock in CreateOrderAction.
 */
final class QuoteOrderAction
{
    /**
     * @param  list<OrderLineData>  $lines
     *
     * @throws UnknownProductException
     */
    public function execute(array $lines, ?string $amountPaid = null): OrderQuote
    {
        if ($lines === []) {
            throw new InvalidArgumentException('A quote must have at least one line.');
        }

        $products = Product::query()
            ->whereIn('id', OrderLineData::productIds($lines))
            ->get()
            ->keyBy('id');

        UnknownProductException::throwIfAnyMissing($lines, $products);

        $pricedOrder = OrderPricing::price($lines, $products);

        return new OrderQuote(
            pricedOrder: $pricedOrder,
            stockOnHand: $products->map(static fn (Product $product): int => $product->stock_on_hand)->all(),
            requestedQuantities: OrderLineData::quantitiesByProduct($lines),
            amountPaid: $amountPaid,
            changeDue: $amountPaid === null ? null : Money::subtract($amountPaid, $pricedOrder->total),
        );
    }
}
