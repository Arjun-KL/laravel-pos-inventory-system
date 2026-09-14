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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            // cascadeOnDelete: a line is meaningless without its order.
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: the line is a financial record that names this
            // product. A sold product cannot be deleted out from under it.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');

            // unit_price and tax_percentage are copied from the product at the
            // moment of sale. This duplication is deliberate, not a missed
            // normalisation: joining to products at read time would let
            // tomorrow's price change rewrite every historical bill. Descriptive
            // fields (name, SKU) are NOT snapshotted — those are read live
            // through the product relationship.
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_percentage', 5, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('line_tax', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_non_negative CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_tax_percentage_range CHECK (tax_percentage >= 0 AND tax_percentage <= 100)');
        // Tax is rounded per line, so this identity must hold on every row.
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_total_is_subtotal_plus_tax CHECK (line_total = line_subtotal + line_tax)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
