<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_a_customer_regardless_of_email_case(): void
    {
        Customer::factory()->create(['name' => 'Thomas Mathew', 'email' => 'thomas@example.com']);

        $this->getJson('/api/customers/lookup?email=THOMAS@Example.com')
            ->assertOk()
            ->assertJsonPath('data.name', 'Thomas Mathew')
            ->assertJsonPath('data.email', 'thomas@example.com');
    }

    public function test_an_unknown_email_returns_null_rather_than_an_error(): void
    {
        $this->getJson('/api/customers/lookup?email=nobody@example.com')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_it_requires_a_valid_email(): void
    {
        $this->getJson('/api/customers/lookup?email=not-an-email')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
