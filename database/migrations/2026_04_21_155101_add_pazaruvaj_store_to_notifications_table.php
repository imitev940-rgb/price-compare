<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('pazaruvaj_store', 150)->nullable()->after('store_id');
            $table->index('pazaruvaj_store');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['pazaruvaj_store']);
            $table->dropColumn('pazaruvaj_store');
        });
    }
};
