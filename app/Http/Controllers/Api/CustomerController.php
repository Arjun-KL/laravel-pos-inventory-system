<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LookupCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class CustomerController extends Controller
{
    /**
     * Powers the billing screen's "name auto-filled if the email exists".
     *
     * This does reveal whether an address belongs to a customer, which the
     * order-history endpoint is careful not to. It is acceptable only because
     * the billing screen is a staff tool at the counter; with authentication in
     * place this endpoint would require a staff role.
     */
    public function lookup(LookupCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()
            ->where('email', Str::lower(trim((string) $request->validated('email'))))
            ->first();

        return new JsonResponse([
            'data' => $customer === null ? null : CustomerResource::make($customer)->resolve($request),
        ]);
    }
}
