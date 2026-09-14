<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\OrderLineRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of the request and nothing else.
 *
 * Deliberately absent: any check that the requested quantity is in stock. A
 * validation rule runs outside the lock and outside the transaction, so its
 * answer is stale by the time it returns — and worse, it makes the code look
 * safe, so reviewers see stock "being checked" and stop looking. Stock is
 * checked exactly once, under lock, in CreateOrderAction. See CLAUDE.md §4.
 */
final class StoreOrderRequest extends FormRequest
{
    use OrderLineRules;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'string', 'email', 'max:255'],
            ...$this->orderLineRules(),
        ];
    }
}
