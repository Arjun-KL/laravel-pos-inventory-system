<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PricedOrder
{
    /** @param list<PricedLine> $lines */
    public function __construct(
        public array $lines,
        public string $subtotal,
        public string $taxTotal,
        public string $total,
    ) {}
}
