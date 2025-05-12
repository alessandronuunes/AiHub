<?php

namespace Modules\OpenAiRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assistant extends Model
{
    protected $fillable = ['company_id', 'assistant_id', 'name', 'instructions', 'file_ids'];

    protected $casts = [
        'file_ids' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}