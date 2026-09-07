<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Checkpoint (t_cp) — aggregates delivered OPH into a checkpoint record.
 * cp_type: 1 = CP1, 2 = CP2. Header + OPH detail lines (t_cp_detail) +
 * loaders (t_cp_loader). Manual entry / correction by Estate Staff.
 */
class Cp extends Model
{
    use HasCompanyScope;

    protected $table = 't_cp';

    public $incrementing = false;
    protected $keyType   = 'string';

    public const TYPE_CP1 = 1;
    public const TYPE_CP2 = 2;

    protected $fillable = [
        'id', 'company_id', 'estate_code', 'division_code',
        'license_number', 'license_number2', 'seal_code', 'receiving_point_code',
        'delivery_note', 'lat', 'long', 'photo',
        'total_bunches', 'total_oph', 'total_loose_fruit',
        'estimate_tonnage', 'actual_tonnage', 'bruto', 'tarra', 'bin_number',
        'cp_type', 'kerani_kirim_emp_code', 'kerani_kirim_emp_name',
        'transporter', 'transporter2', 'vendor_code', 'vendor_name',
        'license_number_vendor', 'license_number_vendor2',
        'sailing_date', 'ship_flag', 'cable_way',
        'is_closed', 'is_deleted', 'adjustment_status', 'integration_status',
        'remark', 'request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'cp_type'           => 'integer',
        'total_bunches'     => 'integer',
        'total_oph'         => 'integer',
        'total_loose_fruit' => 'float',
        'estimate_tonnage'  => 'float',
        'actual_tonnage'    => 'float',
        'bruto'             => 'float',
        'tarra'             => 'float',
        'is_closed'         => 'boolean',
        'is_deleted'        => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CpDetail::class, 'cp_id', 'id');
    }

    public function loaders(): HasMany
    {
        return $this->hasMany(CpLoader::class, 'cp_id', 'id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeType($q, int $type)   { return $q->where('cp_type', $type); }
    public function scopeActual($q)            { return $q->where('is_deleted', false); }
    public function scopeInDivisions($q, ?array $d) { return is_null($d) ? $q : $q->whereIn('division_code', $d); }
}
