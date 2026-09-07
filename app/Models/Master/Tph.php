<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Task / TPH master (m_tph) — harvesting collection points.
 */
class Tph extends Model
{
    use HasCompanyScope;

    protected $table = 'm_tph';

    protected $fillable = [
        'company_id', 'estate_code', 'division_code', 'block_code', 'section_code',
        'tph_code', 'valid_from', 'valid_to', 'latitude', 'longitude', 'tph_palm_total',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'valid_from'     => 'date',
        'valid_to'       => 'date',
        'tph_palm_total' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeByEstate($query, string $estateCode)
    {
        return $query->where('estate_code', $estateCode);
    }
}
