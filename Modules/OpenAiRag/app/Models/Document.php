<?php

namespace Modules\OpenAiRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = ['company_id', 'title', 'category', 'content', 'file_id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}