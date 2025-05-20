<?php

namespace Modules\AiHub\Models;

use Illuminate\Database\Eloquent\Model;

class Assistant extends Model
{
    protected $fillable = [
        'company_id',
        'assistant_id',
        'name',
        'instructions',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vectorStores()
    {
        return $this->belongsToMany(VectorStore::class, 'assistant_vector_store');
    }
}
