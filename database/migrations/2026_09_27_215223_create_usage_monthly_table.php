<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_monthly', function (Blueprint $table) {
            $table->id();
            $table->date('month');
            $table->string('event', 20);
            $table->string('outcome', 20);
            $table->string('source_type', 16);
            $table->char('feed_hash', 64)->default('');
            $table->string('feed_host')->default('');
            $table->string('referrer_host')->default('');
            $table->unsignedInteger('count')->default(0);

            $table->unique([
                'month',
                'event',
                'outcome',
                'source_type',
                'feed_hash',
                'feed_host',
                'referrer_host',
            ], 'usage_monthly_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_monthly');
    }
};
