<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

class Estate extends Model
{
    use HasCompanyScope;

    protected $table = 'm_estate';

    protected $fillable = [
        'company_id',
        'estate_code',
        'estate_name',
        'estate_plant_code',
        'created_by',
        'updated_by',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class, 'estate_code', 'estate_code')
            ->where('company_id', $this->company_id);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('estate_code', 'ilike', "%{$search}%")
              ->orWhere('estate_name', 'ilike', "%{$search}%");
        });
    }
}
