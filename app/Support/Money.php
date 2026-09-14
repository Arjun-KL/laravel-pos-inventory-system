<?php

declare(strict_types=1);

namespace App\Support;

/**
 * All money arithmetic goes through here. `decimal:2` model attributes are
 * PHP strings by design — multiplying or adding them directly silently casts
 * to float and reintroduces the rounding bug this class exists to prevent.
 * Every operation converts to integer paise (cents), computes with exact
 * integer math, and converts back. See CLAUDE.md §3.
 */
final class Money
{
    private function __construct() {}

    /**
     * Parses a NUMERIC(_, 2)-shaped string ("12.50") into integer cents (1250)
     * without ever passing the value through a float.
     */
    public static function toCents(string $amount): int
    {
        $amount = trim($amount);
        $negative = str_starts_with($amount, '-');

        if ($negative) {
            $amount = substr($amount, 1);
        }

        if (! str_contains($amount, '.')) {
            $amount .= '.00';
        }

        [$integerPart, $decimalPart] = explode('.', $amount, 2);
        $integerPart = $integerPart === '' ? '0' : $integerPart;
        $decimalPart = str_pad(substr($decimalPart, 0, 2), 2, '0');

        $cents = ((int) $integerPart) * 100 + (int) $decimalPart;

        return $negative ? -$cents : $cents;
    }

    /**
     * Formats integer cents back into the "12.50" shape a NUMERIC(_, 2)
     * column and a `decimal:2` cast expect.
     */
    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        $integerPart = intdiv($cents, 100);
        $decimalPart = $cents % 100;

        return ($negative ? '-' : '').$integerPart.'.'.str_pad((string) $decimalPart, 2, '0', STR_PAD_LEFT);
    }

    /** "250" and "250.5" become "250.00" and "250.50". */
    public static function normalize(string $amount): string
    {
        return self::fromCents(self::toCents($amount));
    }

    public static function multiplyByQuantity(string $unitPrice, int $quantity): string
    {
        return self::fromCents(self::toCents($unitPrice) * $quantity);
    }

    public static function add(string ...$amounts): string
    {
        $totalCents = array_sum(array_map(self::toCents(...), $amounts));

        return self::fromCents((int) $totalCents);
    }

    public static function subtract(string $minuend, string $subtrahend): string
    {
        return self::fromCents(self::toCents($minuend) - self::toCents($subtrahend));
    }

    /** Compares by value: as strings, "9.50" would sort after "10.00". */
    public static function isLessThan(string $left, string $right): bool
    {
        return self::toCents($left) < self::toCents($right);
    }

    /**
     * Tax for one line, rounded half-up to the nearest cent. Rounding per
     * line (not once on the order total) matches GST invoicing and keeps
     * line_subtotal + line_tax === line_total true on every row.
     */
    public static function taxForLine(string $lineSubtotal, string $taxPercentage): string
    {
        $subtotalCents = self::toCents($lineSubtotal);
        // "18.00" percent -> 1800 hundredths-of-a-percent, kept as an integer
        // for the same reason the money amount is: exactness.
        $percentageHundredths = self::toCents($taxPercentage);

        $numerator = $subtotalCents * $percentageHundredths;
        $denominator = 100 * 100;

        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder * 2 >= $denominator) {
            $quotient++;
        }

        return self::fromCents($quotient);
    }
}
