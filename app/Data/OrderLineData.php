<?php

declare(strict_types=1);

namespace App\Data;

final readonly class OrderLineData
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return list<self>
     */
    public static function listFromArray(array $lines): array
    {
        return array_values(array_map(
            static fn (array $line): self => new self(
                productId: (int) $line['product_id'],
                quantity: (int) $line['quantity'],
            ),
            $lines,
        ));
    }

    /**
     * @param  list<self>  $lines
     * @return list<int>
     */
    public static function productIds(array $lines): array
    {
        return array_values(array_unique(array_map(
            static fn (self $line): int => $line->productId,
            $lines,
        )));
    }

    /**
     * Total quantity requested per product. A request may list the same product
     * on several lines (a cashier scanning an item twice); checking each line
     * against stock separately would let two lines of 6 both pass against a
     * stock of 10. Stock is checked against this total, once.
     *
     * @param  list<self>  $lines
     * @return array<int, int>
     */
    public static function quantitiesByProduct(array $lines): array
    {
        $quantities = [];

        foreach ($lines as $line) {
            $quantities[$line->productId] = ($quantities[$line->productId] ?? 0) + $line->quantity;
        }

        return $quantities;
    }

    /**
     * Which request line indexes reference each product, so a failure can be
     * reported against every line the client actually sent.
     *
     * @param  list<self>  $lines
     * @return array<int, list<int>>
     */
    public static function lineIndexesByProduct(array $lines): array
    {
        $indexes = [];

        foreach ($lines as $index => $line) {
            $indexes[$line->productId][] = $index;
        }

        return $indexes;
    }
}
