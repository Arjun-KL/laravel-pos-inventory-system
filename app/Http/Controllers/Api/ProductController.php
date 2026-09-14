<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\LowStockProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->orderBy('name')
            ->paginate((int) ($request->validated('per_page') ?? self::DEFAULT_PER_PAGE));

        return ProductResource::collection($products);
    }

    public function lowStock(LowStockProductsRequest $request): AnonymousResourceCollection
    {
        // `?? config(...)` rather than a validated() default: an empty
        // "?threshold=" arrives as null, which must fall back, not mean zero.
        $threshold = (int) ($request->validated('threshold') ?? config('inventory.low_stock_threshold'));

        $products = Product::query()
            ->belowStock($threshold)
            ->orderBy('stock_on_hand')
            ->orderBy('id')
            ->paginate((int) ($request->validated('per_page') ?? self::DEFAULT_PER_PAGE));

        return ProductResource::collection($products)
            ->additional(['meta' => ['threshold' => $threshold]]);
    }
}
