<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\OrderLineData;
use App\Data\PricedLine;
use App\Data\PricedOrder;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The single definition of how an order is priced, shared by the quote shown
 * on screen and the order actually placed, so the two cannot disagree.
 */
final class OrderPricing
{
    private function __construct() {}

    /**
     * @param  list<OrderLineData>  $lines
     * @param  Collection<int, Product>  $products  keyed by id, containing every line's product
     */
    public static function price(array $lines, Collection $products): PricedOrder
    {
        $pricedLines = [];

        foreach ($lines as $line) {
            $product = $products->get($line->productId);

            $lineSubtotal = Money::multiplyByQuantity($product->price, $line->quantity);
            $lineTax = Money::taxForLine($lineSubtotal, $product->tax_percentage);

            $pricedLines[] = new PricedLine(
                productId: $line->productId,
                quantity: $line->quantity,
                unitPrice: $product->price,
                taxPercentage: $product->tax_percentage,
                lineSubtotal: $lineSubtotal,
                lineTax: $lineTax,
                lineTotal: Money::add($lineSubtotal, $lineTax),
            );
        }

        // Header totals are sums of the already-rounded line values, so the
        // header always agrees with the lines it is made of.
        $subtotal = Money::add(...array_map(static fn (PricedLine $line): string => $line->lineSubtotal, $pricedLines));
        $taxTotal = Money::add(...array_map(static fn (PricedLine $line): string => $line->lineTax, $pricedLines));

        return new PricedOrder($pricedLines, $subtotal, $taxTotal, Money::add($subtotal, $taxTotal));
    }
}
