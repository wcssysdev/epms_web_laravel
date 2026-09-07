<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Checkpoint loader line (t_cp_loader) — worker/vendor who loaded the CP,
 * with a distribution percentage.
 */
class CpLoader extends Model
{
    protected $table = 't_cp_loader';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'cp_id', 'employee_code', 'employee_name',
        'vendor_code', 'transporter', 'percentage', 'loader_type',
        'integration_status', 'request_id',
    ];

    protected $casts = [
        'percentage'  => 'float',
        'loader_type' => 'integer',
    ];

    public function cp(): BelongsTo
    {
        return $this->belongsTo(Cp::class, 'cp_id', 'id');
    }
}
