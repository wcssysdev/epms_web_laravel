<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Daily work plan created by Assistant Managers / Estate Managers.
 *
 * Approval flow via `is_approved`:
 *   null → draft   (belum dipublish, hanya pembuat yang lihat)
 *   0    → published (menunggu approval Estate Manager)
 *   1    → approved
 *  -1    → rejected
 */
class Workplan extends Model
{
    use HasCompanyScope;

    protected $table = 't_workplan';

    // Primary key is a manually generated string (estate + timestamp + user + "W")
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'workplan_date',
        'estate_code',
        'division_code',
        'activity_code',
        'activity_name',
        'block_code',
        'order_number',
        'auc_number',
        'cost_center',
        'wbs_code',
        'mandor_employee_code',
        'mandor_employee_name',
        'total_hk',
        'total_qty_target',
        'is_approved',
        'approved_by',
        'approved_by_name',
        'approved_at',
        'approval_remark',
        'is_closed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'workplan_date'    => 'date',
        'total_hk'         => 'integer',
        'total_qty_target' => 'float',
        'is_approved'      => 'integer',
        'is_closed'        => 'boolean',
        'approved_at'      => 'datetime',
    ];

    // ── Status constants ────────────────────────────────────────────────────
    const STATUS_DRAFT     = null;
    const STATUS_PUBLISHED = 0;
    const STATUS_APPROVED  = 1;
    const STATUS_REJECTED  = -1;

    // ── Relationships ─────────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkplanMaterial::class, 'workplan_id', 'id');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(WorkplanApprovalLog::class, 'workplan_id', 'id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeForDate($q, string $date)
    {
        return $q->where('workplan_date', $date);
    }

    public function scopeStatus($q, ?int $status)
    {
        return is_null($status)
            ? $q->whereNull('is_approved')
            : $q->where('is_approved', $status);
    }

    public function scopeDraft($q)     { return $q->whereNull('is_approved'); }
    public function scopePublished($q) { return $q->where('is_approved', self::STATUS_PUBLISHED); }
    public function scopeApproved($q)  { return $q->where('is_approved', self::STATUS_APPROVED); }
    public function scopeRejected($q)  { return $q->where('is_approved', self::STATUS_REJECTED); }

    public function scopeByDivision($q, string $division)
    {
        return $q->where('division_code', $division);
    }

    public function scopeCreatedBy($q, string $createdBy)
    {
        return $q->where('created_by', $createdBy);
    }

    // ── Status Helpers ──────────────────────────────────────────────────────
    public function isDraft(): bool     { return is_null($this->is_approved); }
    public function isPublished(): bool { return $this->is_approved === self::STATUS_PUBLISHED; }
    public function isApproved(): bool  { return $this->is_approved === self::STATUS_APPROVED; }
    public function isRejected(): bool  { return $this->is_approved === self::STATUS_REJECTED; }

    /** Draft & rejected plans are still editable; approved/published are locked. */
    public function isEditable(): bool
    {
        return $this->isDraft() || $this->isRejected();
    }

    public function statusLabel(): string
    {
        return match ($this->is_approved) {
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_APPROVED  => 'Approved',
            self::STATUS_REJECTED  => 'Rejected',
            default                => 'Draft',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->is_approved) {
            self::STATUS_PUBLISHED => 'badge-info',
            self::STATUS_APPROVED  => 'badge-success',
            self::STATUS_REJECTED  => 'badge-error',
            default                => 'badge-ghost',
        };
    }
}
