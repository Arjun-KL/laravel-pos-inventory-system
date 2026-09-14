<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Data\OrderLineData;
use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Form Request already rejects unknown product ids, but the actions must
 * stand on their own when called from an Artisan command or a queued job, and a
 * product can be deleted between validation and the query. 422 rather than
 * 404: the order is unprocessable, the endpoint is not missing.
 */
final class UnknownProductException extends Exception
{
    /** @param array<int, list<int>> $lineIndexesByProductId */
    public function __construct(private readonly array $lineIndexesByProductId)
    {
        parent::__construct('One or more items in this order no longer exist.');
    }

    /**
     * @param  list<OrderLineData>  $lines
     * @param  Collection<int, Product>  $products  keyed by id
     */
    public static function throwIfAnyMissing(array $lines, Collection $products): void
    {
        $missing = [];

        foreach (OrderLineData::lineIndexesByProduct($lines) as $productId => $lineIndexes) {
            if (! $products->has($productId)) {
                $missing[$productId] = $lineIndexes;
            }
        }

        if ($missing !== []) {
            throw new self($missing);
        }
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'errors' => $this->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array<string, list<string>> */
    private function errors(): array
    {
        $errors = [];

        foreach ($this->lineIndexesByProductId as $productId => $lineIndexes) {
            foreach ($lineIndexes as $index) {
                $errors["lines.{$index}.product_id"][] = sprintf('Product %d does not exist.', $productId);
            }
        }

        return $errors;
    }
}
