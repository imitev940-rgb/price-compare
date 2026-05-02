<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'pcd_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('pcd_price', 10, 2)->nullable()->after('our_price');
            });
        }
    }

    public function down(): void
    {
        // Не правим rollback на pcd_price (production data)
    }
};
