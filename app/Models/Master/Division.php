<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

class Division extends Model
{
    use HasCompanyScope;

    protected $table = 'm_division';

    protected $fillable = [
        'company_id',
        'estate_code',
        'division_code',
        'division_name',
        'valid_from',
        'valid_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class, 'estate_code', 'estate_code')
            ->where('company_id', $this->company_id);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'division_code', 'division_code')
            ->where('company_id', $this->company_id);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', now()->toDateString());
        });
    }

    public function scopeByEstate($query, string $estateCode)
    {
        return $query->where('estate_code', $estateCode);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('division_code', 'ilike', "%{$search}%")
              ->orWhere('division_name', 'ilike', "%{$search}%")
              ->orWhere('estate_code', 'ilike', "%{$search}%");
        });
    }
}
