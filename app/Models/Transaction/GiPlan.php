<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Goods Issue Plan (simplified Laravel schema — no SAP MO/VRA integration fields).
 * Created by Admin (role 1) / Assistant Manager (role 3). Tri-state smallint flow:
 *   null = draft, 0 = published, 1 = approved, -1 = rejected
 */
class GiPlan extends Model
{
    use HasCompanyScope;

    protected $table = 'tr_gi_plan';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'plan_date', 'estate_code', 'division_code', 'plant_code',
        'sloc_code', 'movement_type',
        'is_approved', 'approved_by', 'approved_by_name', 'approved_at', 'approval_remark',
        'integration_status', 'request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'plan_date'   => 'date',
        'is_approved' => 'integer',
        'approved_at' => 'datetime',
    ];

    const STATUS_DRAFT     = null;
    const STATUS_PUBLISHED = 0;
    const STATUS_APPROVED  = 1;
    const STATUS_REJECTED  = -1;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(GiPlanDetail::class, 'gi_plan_id', 'id');
    }

    public function scopeForDate($q, string $date) { return $q->where('plan_date', $date); }
    public function scopeDraft($q)     { return $q->whereNull('is_approved'); }
    public function scopePublished($q) { return $q->where('is_approved', self::STATUS_PUBLISHED); }
    public function scopeApproved($q)  { return $q->where('is_approved', self::STATUS_APPROVED); }
    public function scopeRejected($q)  { return $q->where('is_approved', self::STATUS_REJECTED); }
    public function scopeCreatedBy($q, string $by) { return $q->where('created_by', $by); }

    public function isDraft(): bool     { return is_null($this->is_approved); }
    public function isPublished(): bool { return $this->is_approved === self::STATUS_PUBLISHED; }
    public function isApproved(): bool  { return $this->is_approved === self::STATUS_APPROVED; }
    public function isRejected(): bool  { return $this->is_approved === self::STATUS_REJECTED; }
    public function isEditable(): bool  { return $this->isDraft() || $this->isRejected(); }

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
            self::STATUS_PUBLISHED => 'bg-blue-100 text-blue-700',
            self::STATUS_APPROVED  => 'bg-green-100 text-green-700',
            self::STATUS_REJECTED  => 'bg-red-100 text-red-700',
            default                => 'bg-gray-100 text-gray-600',
        };
    }
}
