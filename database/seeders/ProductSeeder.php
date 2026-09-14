<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

final class ProductSeeder extends Seeder
{
    /**
     * A fixed catalogue instead of random factory words, so the billing screen
     * and the low-stock report look like a real counter. Several items start
     * below the default threshold of 10 so the low-stock alert has content.
     *
     * The stock here is opening stock. Setting a starting level is not a sale,
     * so it does not go through CreateOrderAction or the stock ledger.
     */
    private const CATALOGUE = [
        // code, name, price, tax %, opening stock
        ['COL-TP-100', 'Colgate Toothpaste 100g', '50.00', '18.00', 40],
        ['PARLE-G-250', 'Parle-G Biscuits 250g', '10.00', '18.00', 120],
        ['AMUL-MILK-1L', 'Amul Milk 1L', '66.00', '0.00', 9],
        ['BRIT-BREAD-400', 'Britannia Bread 400g', '45.00', '0.00', 4],
        ['EGGS-12', 'Farm Eggs (12)', '84.00', '0.00', 2],
        ['TATA-SALT-1KG', 'Tata Salt 1kg', '28.00', '5.00', 60],
        ['AASH-ATTA-5KG', 'Aashirvaad Atta 5kg', '285.00', '5.00', 25],
        ['FORT-OIL-1L', 'Fortune Sunflower Oil 1L', '155.00', '5.00', 30],
        ['MAGGI-70', 'Maggi Noodles 70g', '14.00', '18.00', 200],
        ['SURF-1KG', 'Surf Excel Detergent 1kg', '140.00', '18.00', 18],
        ['DETTOL-125', 'Dettol Soap 125g', '48.00', '18.00', 7],
        ['LAYS-52', 'Lay\'s Classic Salted 52g', '20.00', '12.00', 90],
    ];

    public function run(): void
    {
        foreach (self::CATALOGUE as [$code, $name, $price, $taxPercentage, $stock]) {
            Product::factory()->create([
                'code' => $code,
                'name' => $name,
                'price' => $price,
                'tax_percentage' => $taxPercentage,
                'stock_on_hand' => $stock,
            ]);
        }
    }
}
