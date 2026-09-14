<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Renders itself as a 422, like the other order exceptions. */
final class InsufficientPaymentException extends Exception
{
    public function __construct(
        private readonly string $amountPaid,
        private readonly string $total,
    ) {
        parent::__construct('The amount paid does not cover the order total.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'errors' => [
                'amount_paid' => [sprintf('Amount paid %s is less than the order total %s.', $this->amountPaid, $this->total)],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
