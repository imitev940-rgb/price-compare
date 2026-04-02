<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_histories', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('store_id');
            $table->index('competitor_link_id');
            $table->index('checked_at');
            $table->index(['product_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('price_histories', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['competitor_link_id']);
            $table->dropIndex(['checked_at']);
            $table->dropIndex(['product_id', 'checked_at']);
        });
    }
};