<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\OrderLineRules;
use Illuminate\Foundation\Http\FormRequest;

final class QuoteOrderRequest extends FormRequest
{
    use OrderLineRules;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->orderLineRules();
    }
}
