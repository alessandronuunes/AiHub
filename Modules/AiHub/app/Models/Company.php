<?php

namespace Modules\AiHub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name', 'slug', 'active'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function assistants(): HasMany
    {
        return $this->hasMany(Assistant::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }
}
