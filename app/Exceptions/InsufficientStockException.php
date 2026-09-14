<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Data\StockShortfall;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders itself, so controllers never catch it. Catching it to build a 422
 * would duplicate this mapping in a second place, where it would drift.
 */
final class InsufficientStockException extends Exception
{
    /** @param list<StockShortfall> $shortfalls */
    public function __construct(private readonly array $shortfalls)
    {
        parent::__construct('One or more items are no longer available in the quantity requested.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            // Same envelope as a validation failure so the client needs one
            // error path, not two.
            'errors' => $this->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Every short line at once — a cashier told about one short item at a time
     * makes three round trips for one problem.
     *
     * @return array<string, list<string>>
     */
    private function errors(): array
    {
        $errors = [];

        foreach ($this->shortfalls as $shortfall) {
            foreach ($shortfall->lineIndexes as $index) {
                $errors["lines.{$index}.quantity"][] = $shortfall->message();
            }
        }

        return $errors;
    }
}
