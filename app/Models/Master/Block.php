<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

class Block extends Model
{
    use HasCompanyScope;

    protected $table = 'm_block';

    protected $fillable = [
        'company_id',
        'estate_code',
        'division_code',
        'block_code',
        'block_name',
        'block_hectarage',
        'block_planted_date',
        'valid_from',
        'valid_to',
        'block_state',
        'is_planted',
        'crop_type',
        'total_palm',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'block_hectarage'   => 'float',
        'block_planted_date'=> 'date',
        'valid_from'        => 'date',
        'valid_to'          => 'date',
        'is_planted'        => 'boolean',
        'total_palm'        => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_code', 'division_code')
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

    public function scopeByDivision($query, string $divisionCode)
    {
        return $query->where('division_code', $divisionCode);
    }

    public function scopeByEstate($query, string $estateCode)
    {
        return $query->where('estate_code', $estateCode);
    }

    public function scopeByCropType($query, string $cropType)
    {
        return $query->where('crop_type', $cropType);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('block_code', 'ilike', "%{$search}%")
              ->orWhere('block_name', 'ilike', "%{$search}%")
              ->orWhere('division_code', 'ilike', "%{$search}%");
        });
    }
}
