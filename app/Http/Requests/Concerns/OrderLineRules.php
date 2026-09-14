<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Shape rules shared by placing and quoting an order. Stock is deliberately not
 * among them — see StoreOrderRequest.
 */
trait OrderLineRules
{
    /** @return array<string, list<string>> */
    protected function orderLineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            // Up to two decimal places. Anything finer can't be charged in
            // paise, and would otherwise be silently truncated.
            'amount_paid' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ];
    }
}
