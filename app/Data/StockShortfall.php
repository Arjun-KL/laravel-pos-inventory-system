<?php

declare(strict_types=1);

namespace App\Data;

final readonly class StockShortfall
{
    /** @param list<int> $lineIndexes */
    public function __construct(
        public int $productId,
        public string $code,
        public string $productName,
        public int $requested,
        public int $available,
        public array $lineIndexes,
    ) {}

    public function message(): string
    {
        if ($this->available === 0) {
            return sprintf('%s (%s) is out of stock.', $this->productName, $this->code);
        }

        return sprintf(
            '%s (%s): only %d in stock, %d requested.',
            $this->productName,
            $this->code,
            $this->available,
            $this->requested,
        );
    }
}
