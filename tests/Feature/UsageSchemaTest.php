<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageSchemaTest extends TestCase
{
    #[Test]
    public function usage_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('usage_events', [
            'id',
            'event',
            'outcome',
            'http_status',
            'source_type',
            'feed_hash',
            'feed_host',
            'referrer_host',
            'meeting_count',
            'region_count',
            'upstream_status',
            'chunked',
            'duration_ms',
            'peak_memory_mb',
            'settings',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('feed_labels', [
            'feed_hash',
            'label',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('usage_monthly', [
            'id',
            'month',
            'event',
            'outcome',
            'source_type',
            'feed_hash',
            'feed_host',
            'referrer_host',
            'count',
        ]));
    }
}
