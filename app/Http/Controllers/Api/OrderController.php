<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Orders\CreateOrderAction;
use App\Actions\Orders\QuoteOrderAction;
use App\Data\CustomerData;
use App\Data\OrderLineData;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderHistoryRequest;
use App\Http\Requests\QuoteOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderQuoteResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class OrderController extends Controller
{
    private const PER_PAGE = 15;

    /**
     * No try/catch: the order exceptions render themselves as 422s. Catching
     * them here would duplicate that mapping in a second place, where it would
     * drift.
     */
    public function store(StoreOrderRequest $request, CreateOrderAction $createOrder): JsonResponse
    {
        $validated = $request->validated();

        $order = $createOrder->execute(
            CustomerData::fromArray($validated['customer']),
            OrderLineData::listFromArray($validated['lines']),
            self::amountPaid($validated),
        );

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function quote(QuoteOrderRequest $request, QuoteOrderAction $quoteOrder): OrderQuoteResource
    {
        $validated = $request->validated();

        return OrderQuoteResource::make($quoteOrder->execute(
            OrderLineData::listFromArray($validated['lines']),
            self::amountPaid($validated),
        ));
    }

    public function index(OrderHistoryRequest $request): AnonymousResourceCollection
    {
        // Lower-cased to match how the column is written.
        $email = Str::lower(trim((string) $request->validated('email')));

        $orders = Order::query()
            ->whereRelation('customer', 'email', $email)
            ->with(['customer', 'items.product'])
            ->latest('placed_at')
            ->paginate(self::PER_PAGE);

        return OrderResource::collection($orders);
    }

    /** @param array<string, mixed> $validated */
    private static function amountPaid(array $validated): ?string
    {
        if (! isset($validated['amount_paid'])) {
            return null;
        }

        return Money::normalize((string) $validated['amount_paid']);
    }
}
