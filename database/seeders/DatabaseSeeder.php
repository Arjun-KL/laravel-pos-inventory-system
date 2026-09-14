<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Products and customers only. Sample orders are deliberately not seeded: an
     * order written by a seeder would bypass CreateOrderAction's stock and
     * pricing rules. Place orders through the billing screen or the API instead.
     */
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            CustomerSeeder::class,
        ]);
    }
}
