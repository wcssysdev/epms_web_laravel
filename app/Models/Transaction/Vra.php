<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

/**
 * VRA (Vehicle/Equipment Activity) confirmation — t_vra.
 * ID is application-generated VARCHAR. Guard: integration_status {0,2} = locked.
 */
class Vra extends Model
{
    use HasCompanyScope;

    protected $table     = 't_vra';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'vra_date', 'estate_code', 'license_number',
        'equipment_code', 'order_number', 'meas_point', 'reading_value',
        'confirmation_text', 'plant_code', 'is_approved', 'approved_by', 'approved_at',
        'integration_status', 'remark', 'request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'vra_date'    => 'date',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function scopeActual($q) { return $q; }
}
