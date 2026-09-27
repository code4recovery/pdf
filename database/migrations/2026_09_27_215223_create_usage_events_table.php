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
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 20);
            $table->string('outcome', 20);
            $table->unsignedSmallInteger('http_status');
            $table->string('source_type', 16);
            $table->char('feed_hash', 64)->nullable()->index();
            $table->string('feed_host')->nullable();
            $table->string('referrer_host')->nullable();
            $table->unsignedInteger('meeting_count')->nullable();
            $table->unsignedInteger('region_count')->nullable();
            $table->unsignedSmallInteger('upstream_status')->nullable();
            $table->boolean('chunked')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->unsignedSmallInteger('peak_memory_mb');
            $table->json('settings')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
