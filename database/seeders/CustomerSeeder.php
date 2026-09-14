<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

final class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Known addresses, so the billing screen's auto-fill can be demonstrated.
        Customer::factory()->createMany([
            ['name' => 'Thomas Mathew', 'email' => 'thomas@example.com'],
            ['name' => 'Asha Rao', 'email' => 'asha@example.com'],
            ['name' => 'Ravi Kumar', 'email' => 'ravi@example.com'],
        ]);

        Customer::factory()->count(5)->create();
    }
}
