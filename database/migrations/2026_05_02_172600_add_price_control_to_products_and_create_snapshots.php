<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Добавяме нови колони в products
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('our_price');
            $table->decimal('delivery_price', 10, 2)->nullable()->after('discount_percent');
            $table->decimal('new_price', 10, 2)->nullable()->after('delivery_price');
            $table->timestamp('new_price_updated_at')->nullable()->after('new_price');
        });

        // 2. Нова таблица за snapshots (история на промени)
        Schema::create('price_change_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('product_count')->default(0);
            $table->json('data');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_change_snapshots');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'discount_percent',
                'delivery_price',
                'new_price',
                'new_price_updated_at',
            ]);
        });
    }
};
