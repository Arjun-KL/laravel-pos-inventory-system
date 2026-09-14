<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Lower-cased by the model mutator on write, so a plain unique index
            // gives case-insensitive uniqueness without depending on CITEXT.
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
