<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public feed addresses (never Google Sheets links) so the dashboard can link to each feed.
     */
    public function up(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            $table->string('feed_url', 2048)->nullable()->after('feed_host');
        });

        Schema::table('usage_monthly', function (Blueprint $table) {
            $table->string('feed_url', 2048)->nullable()->after('feed_host');
        });
    }

    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            $table->dropColumn('feed_url');
        });

        Schema::table('usage_monthly', function (Blueprint $table) {
            $table->dropColumn('feed_url');
        });
    }
};
