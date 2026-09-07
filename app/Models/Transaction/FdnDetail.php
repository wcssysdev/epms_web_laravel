<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FDN detail line (t_fdn_detail) — one delivered OPH record per row.
 */
class FdnDetail extends Model
{
    protected $table = 't_fdn_detail';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'fdn_id', 'oph_id', 'oph_block_code', 'oph_tph_code',
        'oph_card_id', 'bunches_delivered', 'loose_fruit_delivered',
        'detail_type', 'integration_status', 'remark',
    ];

    protected $casts = [
        'bunches_delivered'     => 'integer',
        'loose_fruit_delivered' => 'float',
    ];

    public function fdn(): BelongsTo
    {
        return $this->belongsTo(Fdn::class, 'fdn_id', 'id');
    }
}
