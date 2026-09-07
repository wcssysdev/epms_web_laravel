<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * FDN / Delivery Note (t_fdn) — dispatches delivered OPH to a destination.
 * Header + OPH detail lines (t_fdn_detail) + loaders (t_fdn_loader).
 * Manual entry / correction by Estate Staff.
 */
class Fdn extends Model
{
    use HasCompanyScope;

    protected $table = 't_fdn';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'fdn_card_id', 'estate_code', 'division_code',
        'license_number', 'license_number2', 'seal_code',
        'deliver_to_code', 'deliver_to_name', 'delivery_note',
        'lat', 'long', 'photo', 'driver_name',
        'total_bunches', 'total_oph', 'total_loose_fruit',
        'estimate_tonnage', 'actual_tonnage', 'bruto', 'tarra', 'bin_number',
        'fdn_type', 'kerani_kirim_emp_code', 'kerani_kirim_emp_name',
        'vendor_code', 'vendor_name', 'license_number_vendor', 'license_number_vendor2',
        'transporter', 'transporter2', 'sailing_date', 'ship_flag', 'cable_way',
        'sales_order_no', 'sales_order_item',
        'is_closed', 'is_deleted', 'adjustment_status', 'integration_status',
        'remark', 'request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
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
        return $this->hasMany(FdnDetail::class, 'fdn_id', 'id');
    }

    public function loaders(): HasMany
    {
        return $this->hasMany(FdnLoader::class, 'fdn_id', 'id');
    }

    public function scopeActual($q) { return $q->where('is_deleted', false); }
    public function scopeInDivisions($q, ?array $d) { return is_null($d) ? $q : $q->whereIn('division_code', $d); }
}
