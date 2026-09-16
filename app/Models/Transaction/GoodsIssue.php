<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasCompanyScope;

/**
 * Goods Issue header (tr_gi_header).
 *
 * Movement types:
 *  201 - Cost Center (cost_center + gl_account)
 *  221 - Project/WBS  (wbs_code)
 *  261 - Order        (order_number + gl_account)
 */
class GoodsIssue extends Model
{
    use HasCompanyScope;

    protected $table = 'tr_gi_header';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'gi_date', 'estate_code', 'plant_code',
        'sloc_code', 'movement_type', 'gi_document_number',
        'is_approved', 'approved_by', 'approved_at',
        'integration_status', 'request_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'gi_date'     => 'date',
        'approved_at' => 'datetime',
    ];

    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED  = -1;

    public function details(): HasMany
    {
        return $this->hasMany(GoodsIssueDetail::class, 'gi_header_id', 'id');
    }

    public function statusLabel(): string
    {
        return match ((int) $this->integration_status) {
            self::STATUS_SUCCESS => 'Sent to SAP',
            self::STATUS_FAILED  => 'Failed',
            default              => 'Pending',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ((int) $this->integration_status) {
            self::STATUS_SUCCESS => 'bg-green-100 text-green-700',
            self::STATUS_FAILED  => 'bg-red-100 text-red-700',
            default              => 'bg-yellow-100 text-yellow-700',
        };
    }

    public function isEditable(): bool
    {
        return (int) $this->integration_status !== self::STATUS_SUCCESS;
    }

    public function scopeByCompany($query, $companyId)
    {
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }
}
