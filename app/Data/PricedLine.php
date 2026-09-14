<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PricedLine
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public string $unitPrice,
        public string $taxPercentage,
        public string $lineSubtotal,
        public string $lineTax,
        public string $lineTotal,
    ) {}

    /** @return array<string, int|string> */
    public function toOrderItemAttributes(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'tax_percentage' => $this->taxPercentage,
            'line_subtotal' => $this->lineSubtotal,
            'line_tax' => $this->lineTax,
            'line_total' => $this->lineTotal,
        ];
    }
}
