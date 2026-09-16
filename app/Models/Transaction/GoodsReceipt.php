<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasCompanyScope;

/**
 * Goods Receipt header (tr_gr_header).
 */
class GoodsReceipt extends Model
{
    use HasCompanyScope;

    protected $table = 'tr_gr_header';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'gr_date', 'plant_code', 'sloc_code',
        'po_number', 'gr_document_number',
        'integration_status', 'request_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'gr_date' => 'date',
    ];

    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED  = -1;

    public function details(): HasMany
    {
        return $this->hasMany(GoodsReceiptDetail::class, 'gr_header_id', 'id');
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
