<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Checkpoint detail line (t_cp_detail) — one delivered OPH record per row.
 */
class CpDetail extends Model
{
    protected $table = 't_cp_detail';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'cp_id', 'oph_id', 'oph_block_code', 'oph_tph_code',
        'oph_card_id', 'oph_platform_no', 'bunches_delivered', 'loose_fruit_delivered',
        'detail_type', 'integration_status', 'remark',
    ];

    protected $casts = [
        'bunches_delivered'     => 'integer',
        'loose_fruit_delivered' => 'float',
    ];

    public function cp(): BelongsTo
    {
        return $this->belongsTo(Cp::class, 'cp_id', 'id');
    }
}
