<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FDN loader line (t_fdn_loader) — worker/vendor who loaded the FDN.
 */
class FdnLoader extends Model
{
    protected $table = 't_fdn_loader';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'fdn_id', 'employee_code', 'employee_name',
        'vendor_code', 'transporter', 'percentage', 'loader_type',
        'integration_status', 'request_id',
    ];

    protected $casts = [
        'percentage'  => 'float',
        'loader_type' => 'integer',
    ];

    public function fdn(): BelongsTo
    {
        return $this->belongsTo(Fdn::class, 'fdn_id', 'id');
    }
}
