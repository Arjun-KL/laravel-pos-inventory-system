<?php

declare(strict_types=1);

namespace App\Data;

final readonly class OrderQuote
{
    /**
     * @param  array<int, int>  $stockOnHand  product id => stock at the time of the quote
     * @param  array<int, int>  $requestedQuantities  product id => total quantity across lines
     * @param  string|null  $changeDue  signed: negative means the customer still owes that much
     */
    public function __construct(
        public PricedOrder $pricedOrder,
        public array $stockOnHand,
        public array $requestedQuantities,
        public ?string $amountPaid,
        public ?string $changeDue,
    ) {}

    /** Advisory only — the binding check happens under lock when the order is placed. */
    public function isAvailable(int $productId): bool
    {
        return $this->requestedQuantities[$productId] <= $this->stockOnHand[$productId];
    }
}
