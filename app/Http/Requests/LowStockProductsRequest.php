<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LowStockProductsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Optional per-request override of config('inventory.low_stock_threshold').
            'threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
