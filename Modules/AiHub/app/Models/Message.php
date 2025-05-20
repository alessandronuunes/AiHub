<?php

namespace Modules\AiHub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'thread_id',
        'message_id',
        'role',
        'content',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
