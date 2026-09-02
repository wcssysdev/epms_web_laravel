<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Harvesting plan (palm) created per division/block per date.
 *
 * Approval flow via `is_approved`:
 *   null → draft
 *   0    → published (menunggu approval)
 *   1    → approved
 *  -1    → rejected
 */
class HarvestingPlan extends Model
{
    use HasCompanyScope;

    protected $table = 't_harvesting_plan';

    protected $fillable = [
        'company_id',
        'plan_date',
        'estate_code',
        'division_code',
        'block_code',
        'total_hk',
        'qty_target',
        'ha',
        'assistant_emp_code',
        'assistant_emp_name',
        'is_approved',
        'approved_by',
        'approved_by_name',
        'approval_remark',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'plan_date'   => 'date',
        'total_hk'    => 'integer',
        'qty_target'  => 'integer',
        'is_approved' => 'integer',
        'approved_at' => 'datetime',
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

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeForDate($q, string $date)
    {
        return $q->where('plan_date', $date);
    }

    public function scopeByDivision($q, string $division)
    {
        return $q->where('division_code', $division);
    }

    public function scopePublished($q) { return $q->where('is_approved', self::STATUS_PUBLISHED); }
    public function scopeApproved($q)  { return $q->where('is_approved', self::STATUS_APPROVED); }
    public function scopeRejected($q)  { return $q->where('is_approved', self::STATUS_REJECTED); }

    // ── Status Helpers ──────────────────────────────────────────────────────
    public function isDraft(): bool     { return is_null($this->is_approved); }
    public function isPublished(): bool { return $this->is_approved === self::STATUS_PUBLISHED; }
    public function isApproved(): bool  { return $this->is_approved === self::STATUS_APPROVED; }
    public function isRejected(): bool  { return $this->is_approved === self::STATUS_REJECTED; }

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
