<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competitor_link_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('our_price', 10, 2)->nullable();
            $table->decimal('competitor_price', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->decimal('percent_difference', 10, 2)->nullable();

            $table->string('best_competitor')->nullable();
            $table->string('position')->nullable();
            $table->string('status')->nullable();

            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};