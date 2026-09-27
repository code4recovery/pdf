<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedLabel extends Model
{
    /**
     * @var string
     */
    protected $primaryKey = 'feed_hash';

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'feed_hash',
        'label',
    ];
}
