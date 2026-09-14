<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds order headers for read-back tests only. It writes no lines and touches
 * no stock, because an order created this way never went through
 * CreateOrderAction. Any test about stock or totals must go through the API or
 * the action — a factory that wrote orders directly would only test itself.
 *
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $subtotal = number_format(fake()->numberBetween(1000, 500000) / 100, 2, '.', '');
        $taxTotal = Money::taxForLine($subtotal, '18.00');

        return [
            'order_number' => 'ORD-'.Str::upper((string) Str::ulid()),
            'customer_id' => Customer::factory(),
            'status' => Order::STATUS_PLACED,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => Money::add($subtotal, $taxTotal),
            'placed_at' => now(),
        ];
    }
}
