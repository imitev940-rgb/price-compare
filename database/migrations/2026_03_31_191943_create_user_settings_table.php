<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');

            $table->string('language', 10)->default('bg');
            $table->string('theme', 20)->default('light');
            $table->boolean('notifications_enabled')->default(true);
            $table->integer('refresh_interval')->default(60);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};