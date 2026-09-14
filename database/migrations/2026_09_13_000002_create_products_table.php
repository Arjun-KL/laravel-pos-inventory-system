<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->integer('stock_on_hand')->default(0);
            $table->timestamps();

            // The low-stock report filters and sorts on this.
            $table->index('stock_on_hand');
        });

        // Postgres has no unsigned integers: unsignedInteger() emits a plain
        // integer with no constraint, so non-negativity is written by hand.
        // This is the backstop if a bug ever bypasses CreateOrderAction — it
        // turns silent negative inventory into a loud failed write.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_on_hand_non_negative CHECK (stock_on_hand >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_tax_percentage_range CHECK (tax_percentage >= 0 AND tax_percentage <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
