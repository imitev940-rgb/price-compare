<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pazaruvaj_offers', function (Blueprint $table) {
            $table->text('offer_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pazaruvaj_offers', function (Blueprint $table) {
            $table->string('offer_url')->nullable()->change();
        });
    }
};