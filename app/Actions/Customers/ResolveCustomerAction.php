<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Data\CustomerData;
use App\Models\Customer;

final class ResolveCustomerAction
{
    /**
     * Email is the customer's identity. A name typed at the counter is used
     * only when the customer is new; an existing customer keeps the name on
     * file, so a typo at checkout cannot rename them.
     *
     * firstOrCreate falls back to createOrFirst on a miss, so two first-time
     * orders racing on the same new email resolve to one row instead of one of
     * them failing on the unique index.
     */
    public function execute(CustomerData $data): Customer
    {
        return Customer::query()->firstOrCreate(
            ['email' => $data->email],
            ['name' => $data->name],
        );
    }
}
