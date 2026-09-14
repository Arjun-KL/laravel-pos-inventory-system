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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            // restrictOnDelete: an order is a financial record. Deleting a
            // customer must not silently delete what they were charged.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2);
            $table->decimal('total', 12, 2);
            // Cash handed over at the counter and the change returned. Nullable
            // because the brief's order API does not require payment details.
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->decimal('change_due', 12, 2)->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();

            // Exactly how order history is queried: one customer, newest first.
            $table->index(['customer_id', 'created_at']);
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_non_negative CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_tax_total_non_negative CHECK (tax_total >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_is_subtotal_plus_tax CHECK (total = subtotal + tax_total)');
        DB::statement(<<<'SQL'
            ALTER TABLE orders ADD CONSTRAINT orders_payment_is_consistent CHECK (
                (amount_paid IS NULL AND change_due IS NULL)
                OR (amount_paid >= total AND change_due = amount_paid - total)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
