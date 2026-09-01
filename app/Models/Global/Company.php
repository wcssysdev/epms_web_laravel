<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $table = 'm_company';

    protected $fillable = ['country_id', 'company_code', 'company_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function config(): HasOne
    {
        return $this->hasOne(CompanyConfig::class, 'company_id');
    }

    public function userAccess(): HasMany
    {
        return $this->hasMany(\App\Models\Transaction\UserAccess::class, 'company_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function getCountryCodeAttribute(): string
    {
        return $this->country?->code ?? '';
    }
}
