<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasCompanyScope;

/**
 * Goods Issue detail line (tr_gi_detail).
 */
class GoodsIssueDetail extends Model
{
    use HasCompanyScope;

    protected $table = 'tr_gi_detail';
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'gi_header_id',
        'material_code', 'material_name',
        'qty', 'uom',
        'cost_center', 'wbs_code', 'order_number', 'gl_account',
        'integration_status',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class, 'gi_header_id', 'id');
    }
}
