<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Customers\ResolveCustomerAction;
use App\Data\CustomerData;
use App\Data\OrderLineData;
use App\Data\PricedLine;
use App\Data\PricedOrder;
use App\Data\StockShortfall;
use App\Exceptions\InsufficientPaymentException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\UnknownProductException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Money;
use App\Support\OrderPricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only place in this application that changes stock.
 *
 * Everything here happens under a row lock inside a transaction: lock, verify
 * against what the lock returned, price, persist, deduct. See CLAUDE.md §1, §4.
 */
final class CreateOrderAction
{
    public function __construct(
        private readonly ResolveCustomerAction $resolveCustomer,
    ) {}

    /**
     * @param  list<OrderLineData>  $lines
     * @param  string|null  $amountPaid  cash handed over at the counter ("250.00"), or null if not recorded
     *
     * @throws InsufficientStockException
     * @throws InsufficientPaymentException
     * @throws UnknownProductException
     */
    public function execute(CustomerData $customerData, array $lines, ?string $amountPaid = null): Order
    {
        if ($lines === []) {
            throw new InvalidArgumentException('An order must have at least one line.');
        }

        // Resolved before the transaction opens. It needs no product lock, and
        // every millisecond a product row stays locked is a millisecond a
        // concurrent sale of that product is blocked.
        $customer = $this->resolveCustomer->execute($customerData);

        $order = DB::transaction(function () use ($customer, $lines, $amountPaid): Order {
            $products = $this->lockProducts($lines);

            UnknownProductException::throwIfAnyMissing($lines, $products);
            $this->guardAgainstInsufficientStock($lines, $products);

            // Priced from the locked rows, not from the quote the screen showed:
            // a price could have changed since, and the lock is the moment of sale.
            $pricedOrder = OrderPricing::price($lines, $products);
            $this->guardAgainstInsufficientPayment($pricedOrder, $amountPaid);

            $order = $this->persistOrder($customer, $pricedOrder, $amountPaid);
            $this->deductStock($order, $lines, $products);

            // afterCommit: a worker is a separate process with its own
            // connection. Dispatched mid-transaction, it can start before
            // COMMIT and query an order row that is not visible to it yet.
            SendOrderConfirmation::dispatch($order->id)->afterCommit();

            return $order;
        }, attempts: 3);

        // Eager loading is read-only work, so it waits until the locks are
        // released rather than holding them for the sake of a response body.
        return $order->load(['customer', 'items.product']);
    }

    /**
     * @param  list<OrderLineData>  $lines
     * @return Collection<int, Product>
     */
    private function lockProducts(array $lines): Collection
    {
        $productIds = OrderLineData::productIds($lines);

        // Ascending id, always. Two orders holding the same two products in
        // opposite order deadlock if each takes its locks in request order.
        sort($productIds);

        return Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * The one and only stock check, made against the values the locked query
     * returned. The same check in a Form Request would run outside the lock
     * and outside the transaction, where its answer is stale before it returns
     * — while making the code look safe to a reviewer.
     *
     * @param  list<OrderLineData>  $lines
     * @param  Collection<int, Product>  $products
     */
    private function guardAgainstInsufficientStock(array $lines, Collection $products): void
    {
        $lineIndexes = OrderLineData::lineIndexesByProduct($lines);
        $shortfalls = [];

        foreach (OrderLineData::quantitiesByProduct($lines) as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product->hasStockFor($quantity)) {
                continue;
            }

            $shortfalls[] = new StockShortfall(
                productId: $productId,
                code: $product->code,
                productName: $product->name,
                requested: $quantity,
                available: $product->stock_on_hand,
                lineIndexes: $lineIndexes[$productId],
            );
        }

        // Every short line at once, so one problem costs one round trip.
        if ($shortfalls !== []) {
            throw new InsufficientStockException($shortfalls);
        }
    }

    private function guardAgainstInsufficientPayment(PricedOrder $pricedOrder, ?string $amountPaid): void
    {
        if ($amountPaid !== null && Money::isLessThan($amountPaid, $pricedOrder->total)) {
            throw new InsufficientPaymentException($amountPaid, $pricedOrder->total);
        }
    }

    private function persistOrder(Customer $customer, PricedOrder $pricedOrder, ?string $amountPaid): Order
    {
        $order = $customer->orders()->create([
            'order_number' => 'ORD-'.Str::upper((string) Str::ulid()),
            'status' => Order::STATUS_PLACED,
            'subtotal' => $pricedOrder->subtotal,
            'tax_total' => $pricedOrder->taxTotal,
            'total' => $pricedOrder->total,
            'amount_paid' => $amountPaid,
            'change_due' => $amountPaid === null ? null : Money::subtract($amountPaid, $pricedOrder->total),
            'placed_at' => now(),
        ]);

        // Price and tax rate are copied onto each line, not joined at read time:
        // a financial record must reflect what was actually charged, so
        // tomorrow's price change cannot rewrite yesterday's bill.
        $order->items()->createMany(array_map(
            static fn (PricedLine $line): array => $line->toOrderItemAttributes(),
            $pricedOrder->lines,
        ));

        return $order;
    }

    /**
     * @param  list<OrderLineData>  $lines
     * @param  Collection<int, Product>  $products
     */
    private function deductStock(Order $order, array $lines, Collection $products): void
    {
        foreach (OrderLineData::quantitiesByProduct($lines) as $productId => $quantity) {
            $product = $products->get($productId);

            // Safe to compute from the in-memory value: this row is locked, so
            // nothing else can have moved it since the lock was taken.
            $stockAfter = $product->stock_on_hand - $quantity;

            $product->update(['stock_on_hand' => $stockAfter]);

            $order->stockMovements()->create([
                'product_id' => $productId,
                'reason' => StockMovement::REASON_ORDER_PLACED,
                'quantity_change' => -$quantity,
                'stock_after' => $stockAfter,
            ]);
        }
    }
}
