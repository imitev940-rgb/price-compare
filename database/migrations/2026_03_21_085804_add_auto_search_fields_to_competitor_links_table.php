<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_links', function (Blueprint $table) {
            $table->string('matched_title')->nullable()->after('product_url');
            $table->boolean('is_auto_found')->default(false)->after('is_active');
            $table->string('search_status')->nullable()->after('is_auto_found');
            $table->decimal('match_score', 5, 2)->nullable()->after('search_status');
            $table->text('last_error')->nullable()->after('match_score');
        });
    }

    public function down(): void
    {
        Schema::table('competitor_links', function (Blueprint $table) {
            $table->dropColumn([
                'matched_title',
                'is_auto_found',
                'search_status',
                'match_score',
                'last_error',
            ]);
        });
    }
};