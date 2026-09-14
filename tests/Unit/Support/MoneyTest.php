<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_converts_decimal_strings_to_cents_and_back_unchanged(): void
    {
        $this->assertSame(1250, Money::toCents('12.50'));
        $this->assertSame(0, Money::toCents('0.00'));
        $this->assertSame(5, Money::toCents('0.05'));
        $this->assertSame(100000000000, Money::toCents('1000000000.00'));

        $this->assertSame('12.50', Money::fromCents(1250));
        $this->assertSame('0.05', Money::fromCents(5));
        $this->assertSame('0.00', Money::fromCents(0));
    }

    public function test_multiplying_by_a_quantity_stays_exact_where_a_float_would_not(): void
    {
        // 0.1 * 3 is 0.30000000000000004 in binary floating point. This is the
        // entire reason Money exists.
        $this->assertSame('0.30', Money::multiplyByQuantity('0.10', 3));
        $this->assertSame('149.97', Money::multiplyByQuantity('49.99', 3));
        $this->assertSame('0.00', Money::multiplyByQuantity('0.00', 99));
    }

    public function test_adding_amounts_stays_exact(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.10', '0.10'));
        $this->assertSame('349.97', Money::add('200.00', '149.97'));
        $this->assertSame('0.00', Money::add());
    }

    public function test_subtracting_can_go_negative_and_stays_exact(): void
    {
        $this->assertSame('14.00', Money::subtract('250.00', '236.00'));
        $this->assertSame('0.00', Money::subtract('0.30', '0.30'));
        $this->assertSame('-18.00', Money::subtract('100', '118.00'));
        $this->assertSame('-0.05', Money::subtract('0.00', '0.05'));
    }

    public function test_it_normalises_amounts_to_two_decimal_places(): void
    {
        $this->assertSame('250.00', Money::normalize('250'));
        $this->assertSame('250.50', Money::normalize('250.5'));
        $this->assertSame('0.10', Money::normalize('0.1'));
    }

    public function test_it_compares_amounts_by_value_not_as_strings(): void
    {
        // As strings, "9.50" sorts after "10.00".
        $this->assertTrue(Money::isLessThan('9.50', '10.00'));
        $this->assertFalse(Money::isLessThan('100', '100.00'));
        $this->assertFalse(Money::isLessThan('236.01', '236.00'));
    }

    public function test_line_tax_rounds_half_up(): void
    {
        $this->assertSame('18.00', Money::taxForLine('100.00', '18.00'));

        // 7.4985 rounds up to 7.50 at two decimal places.
        $this->assertSame('7.50', Money::taxForLine('149.97', '5.00'));

        // Exactly half a cent must round up, not to even.
        $this->assertSame('0.01', Money::taxForLine('0.10', '5.00'));
        $this->assertSame('0.03', Money::taxForLine('1.00', '2.50'));
    }

    public function test_a_zero_tax_rate_produces_no_tax(): void
    {
        $this->assertSame('0.00', Money::taxForLine('149.97', '0.00'));
    }
}
