<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ първо чистим duplicates (ако има)
        DB::statement("
            DELETE cl1 FROM competitor_links cl1
            INNER JOIN competitor_links cl2
            WHERE
                cl1.id > cl2.id AND
                cl1.product_id = cl2.product_id AND
                cl1.store_id = cl2.store_id
        ");

        Schema::table('competitor_links', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('store_id');
            $table->index('is_active');
            $table->index('last_checked_at');
            $table->index('product_url');

            $table->unique(['product_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::table('competitor_links', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_checked_at']);
            $table->dropIndex(['product_url']);

            $table->dropUnique(['product_id', 'store_id']);
        });
    }
};