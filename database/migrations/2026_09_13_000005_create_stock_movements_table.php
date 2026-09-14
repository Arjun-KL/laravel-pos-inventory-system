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
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            // restrictOnDelete: the ledger explains how current stock got to be
            // what it is. Losing rows silently would make it unauditable.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // nullOnDelete: the order is optional context on a movement, not its
            // reason for existing. Stock adjustments have no order at all.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 32);
            $table->integer('quantity_change');
            $table->integer('stock_after');
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_change_not_zero CHECK (quantity_change <> 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_stock_after_non_negative CHECK (stock_after >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
