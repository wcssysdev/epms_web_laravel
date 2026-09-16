<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasCompanyScope;

/**
 * Goods Receipt detail line (tr_gr_detail).
 */
class GoodsReceiptDetail extends Model
{
    use HasCompanyScope;

    protected $table = 'tr_gr_detail';
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'gr_header_id',
        'material_code', 'material_name',
        'qty', 'uom',
        'integration_status',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'gr_header_id', 'id');
    }
}
