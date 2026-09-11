<?php

namespace App\Models\Transaction;

use App\Models\Global\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalSubstitution extends Model
{
    protected $table = 'approval_substitution';

    public const TYPE_MANAGER = 1;
    public const TYPE_MASTER_DATA = 2;

    protected $fillable = [
        'company_id',
        'employee_code',
        'employee_name',
        'target_employee_code',
        'target_employee_name',
        'substitution_from',
        'substitution_to',
        'substitution_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'substitution_from' => 'date',
        'substitution_to'   => 'date',
        'substitution_type' => 'integer',
        'company_id'        => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_code', 'id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_employee_code', 'id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeManagerSubstitution($query)
    {
        return $query->where('substitution_type', self::TYPE_MANAGER);
    }

    public function scopeMasterDataSubstitution($query)
    {
        return $query->where('substitution_type', self::TYPE_MASTER_DATA);
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        if ($companyId) {
            return $query->where('company_id', $companyId);
        }
        return $query;
    }

    // ── Accessors & Helpers ───────────────────────────────────────────────────

    public function getStatusAttribute(): string
    {
        $today = date('Y-m-d');
        $from = $this->substitution_from ? $this->substitution_from->format('Y-m-d') : null;
        $to = $this->substitution_to ? $this->substitution_to->format('Y-m-d') : null;

        if ($from && $today < $from) {
            return '<span class="label label-sm label-info">Scheduled</span>';
        } elseif ($from && $to && $today >= $from && $today <= $to) {
            return '<span class="label label-sm label-success">Active</span>';
        } else {
            return '<span class="label label-sm label-default">Expired</span>';
        }
    }

    public function getFormattedFromAttribute(): string
    {
        return $this->substitution_from ? $this->substitution_from->format('d-m-Y') : '-';
    }

    public function getFormattedToAttribute(): string
    {
        return $this->substitution_to ? $this->substitution_to->format('d-m-Y') : '-';
    }

    public function getIsActiveTodayAttribute(): bool
    {
        $today = date('Y-m-d');
        $from = $this->substitution_from ? $this->substitution_from->format('Y-m-d') : null;
        $to = $this->substitution_to ? $this->substitution_to->format('Y-m-d') : null;

        return $from && $to && $today >= $from && $today <= $to;
    }
}
