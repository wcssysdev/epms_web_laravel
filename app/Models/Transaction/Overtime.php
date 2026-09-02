<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Overtime record. Approved by Assistant Manager (role 3, division-scoped).
 *
 * Laravel schema uses boolean is_approved (not the CI4 tri-state smallint):
 *   is_approved = null/false, approved_at = null  → Pending
 *   is_approved = true                            → Approved
 *   is_approved = false, approved_at != null      → Rejected
 */
class Overtime extends Model
{
    use HasCompanyScope;

    protected $table = 't_overtime';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'overtime_date', 'estate_code', 'division_code',
        'mandor_employee_code', 'mandor_employee_name', 'employee_code', 'employee_name',
        'activity_code', 'activity_name', 'block_code', 'order_number', 'cost_center',
        'start_time', 'end_time', 'duration_hours',
        'is_approved', 'approved_by', 'approved_at', 'is_closed', 'integration_status',
        'remark', 'request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'overtime_date'  => 'date',
        'duration_hours' => 'float',
        'is_approved'    => 'boolean',
        'is_closed'      => 'boolean',
        'approved_at'    => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // ── Status scopes (boolean schema) ────────────────────────────────────────
    public function scopePending($q)  { return $q->whereNull('approved_at'); }
    public function scopeApproved($q) { return $q->where('is_approved', true)->whereNotNull('approved_at'); }
    public function scopeRejected($q) { return $q->where('is_approved', false)->whereNotNull('approved_at'); }

    public function scopeForDate($q, string $date) { return $q->where('overtime_date', $date); }
    public function scopeInDivisions($q, ?array $divisions)
    {
        return is_null($divisions) ? $q : $q->whereIn('division_code', $divisions);
    }

    // ── Status helpers ────────────────────────────────────────────────────────
    public function isPending(): bool  { return is_null($this->approved_at); }
    public function isApproved(): bool { return $this->is_approved && !is_null($this->approved_at); }
    public function isRejected(): bool { return !$this->is_approved && !is_null($this->approved_at); }

    public function statusLabel(): string
    {
        if ($this->isApproved()) return 'Approved';
        if ($this->isRejected()) return 'Rejected';
        return 'Pending';
    }
}
