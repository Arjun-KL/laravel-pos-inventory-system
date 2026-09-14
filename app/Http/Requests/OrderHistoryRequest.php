<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OrderHistoryRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Deliberately not `exists:customers,email`: an unknown address
            // gets an empty list, not an error that confirms who shops here.
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
