<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'm_country';

    protected $fillable = ['code', 'name', 'prefix', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'country_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
