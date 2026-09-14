<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class BillingScreenTest extends TestCase
{
    public function test_the_billing_screen_is_served_at_the_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Store Billing')
            ->assertSee('Low Stock Alert')
            ->assertSee('Generate Bill');
    }
}
