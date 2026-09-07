<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Coconut FDN / Delivery Note (t_coconut_fdn) — dispatches harvested coconut
 * (Harvesting Chit / coconut OPH) to a destination. Header + detail lines.
 */
class CoconutFdn extends Model
{
    use HasCompanyScope;

    protected $table = 't_coconut_fdn';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'estate_code', 'division_code',
        'sales_order_no', 'sales_order_item', 'receiving_point_code',
        'license_number', 'driver_name', 'vehicle_vendor_code',
        'kerani_kirim_emp_code', 'kerani_kirim_emp_name',
        'delivery_note', 'fdn_card_id', 'lat', 'long', 'photo',
        'total_oph', 'bruto', 'tarra', 'actual_tonnage', 'total_customer_qty',
        'destination', 'is_nursery', 'is_stock',
        'is_closed', 'is_deleted', 'adjustment_status', 'integration_status',
        'remark', 'request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'total_oph'          => 'integer',
        'bruto'              => 'float',
        'tarra'              => 'float',
        'actual_tonnage'     => 'float',
        'total_customer_qty' => 'float',
        'is_nursery'         => 'boolean',
        'is_stock'           => 'boolean',
        'is_closed'          => 'boolean',
        'is_deleted'         => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CoconutFdnDetail::class, 'coconut_fdn_id', 'id');
    }

    public function scopeActual($q) { return $q->where('is_deleted', false); }
    public function scopeInDivisions($q, ?array $d) { return is_null($d) ? $q : $q->whereIn('division_code', $d); }
}
