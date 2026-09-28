<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    /** @use HasFactory<\Database\Factories\UsageEventFactory> */
    use HasFactory;

    /**
     * This table has no `updated_at` column — usage events are write-once.
     */
    const UPDATED_AT = null;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'event',
        'outcome',
        'http_status',
        'source_type',
        'feed_hash',
        'feed_host',
        'feed_url',
        'referrer_host',
        'meeting_count',
        'region_count',
        'upstream_status',
        'chunked',
        'duration_ms',
        'peak_memory_mb',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'chunked' => 'boolean',
        ];
    }
}
