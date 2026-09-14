<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_email_returns_an_empty_list_rather_than_a_404(): void
    {
        // A 404 here would confirm whether an address shops with us.
        $this->getJson('/api/orders?email=nobody@example.com')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_returns_only_the_orders_of_the_given_customer(): void
    {
        $asha = Customer::factory()->create(['email' => 'asha@example.com']);
        Order::factory()->count(2)->for($asha)->create();
        Order::factory()->count(3)->for(Customer::factory()->create())->create();

        $this->getJson('/api/orders?email=asha@example.com')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_the_collection_is_paginated_from_day_one(): void
    {
        $asha = Customer::factory()->create(['email' => 'asha@example.com']);
        Order::factory()->count(20)->for($asha)->create();

        $this->getJson('/api/orders?email=asha@example.com')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_the_email_is_matched_regardless_of_case(): void
    {
        $asha = Customer::factory()->create(['email' => 'asha@example.com']);
        Order::factory()->for($asha)->create();

        $this->getJson('/api/orders?email=ASHA@Example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_rejects_a_request_without_an_email(): void
    {
        $this->getJson('/api/orders')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_a_request_not_asking_for_json_still_gets_a_json_error_not_a_redirect(): void
    {
        $this->get('/api/orders', ['Accept' => 'text/html'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
