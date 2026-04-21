<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->decimal('old_price', 10, 2)->nullable()->after('message');
            $table->decimal('new_price', 10, 2)->nullable()->after('old_price');
            $table->decimal('price_change_percent', 6, 2)->nullable()->after('new_price');

            $table->index(['store_id', 'created_at']);
            $table->index('price_change_percent');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex(['store_id', 'created_at']);
            $table->dropIndex(['price_change_percent']);
            $table->dropColumn(['store_id', 'old_price', 'new_price', 'price_change_percent']);
        });
    }
};
