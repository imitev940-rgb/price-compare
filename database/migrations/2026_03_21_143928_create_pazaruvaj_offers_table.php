<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pazaruvaj_offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_link_id')->nullable()->constrained('competitor_links')->nullOnDelete();

            $table->string('store_name');
            $table->string('offer_title')->nullable();
            $table->string('offer_url')->nullable();

            $table->decimal('price', 10, 2)->nullable();
            $table->integer('position')->nullable();

            $table->boolean('is_lowest')->default(false);
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pazaruvaj_offers');
    }
};