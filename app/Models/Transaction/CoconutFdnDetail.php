<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coconut FDN detail line (t_coconut_fdn_detail) — one delivered coconut OPH
 * (Harvesting Chit) per row, with customer nut quantity.
 */
class CoconutFdnDetail extends Model
{
    protected $table = 't_coconut_fdn_detail';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'coconut_fdn_id', 'coconut_oph_id', 'coconut_oph_card_id',
        'total_customer_nut_qty', 'integration_status', 'remark',
    ];

    protected $casts = [
        'total_customer_nut_qty' => 'float',
    ];

    public function fdn(): BelongsTo
    {
        return $this->belongsTo(CoconutFdn::class, 'coconut_fdn_id', 'id');
    }
}
