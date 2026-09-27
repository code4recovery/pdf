<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageMonthly extends Model
{
    /**
     * @var string
     */
    protected $table = 'usage_monthly';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'month',
        'event',
        'outcome',
        'source_type',
        'feed_hash',
        'feed_host',
        'referrer_host',
        'count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }
}
