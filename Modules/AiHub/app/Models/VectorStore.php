<?php

namespace Modules\AiHub\Models;

use Illuminate\Database\Eloquent\Model;

class VectorStore extends Model
{
    protected $fillable = [
        'company_id',
        'vector_store_id',
        'name',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
