<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Work done record. Unplanned activities (is_planned = false) are approved by
 * the Assistant Manager (role 3, division-scoped) via the Unplanned Activity flow.
 *
 * Boolean is_approved schema (same convention as Overtime):
 *   approved_at null            → Pending
 *   is_approved true            → Approved
 *   is_approved false + at set  → Rejected
 */
class Workdone extends Model
{
    use HasCompanyScope;

    protected $table = 't_workdone';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'workdone_date', 'estate_code', 'plant_code', 'division_code',
        'activity_code', 'activity_name', 'activity_uom', 'block_code', 'order_number',
        'auc_number', 'cost_center', 'wbs_code',
        'mandor_employee_code', 'mandor_employee_name', 'employee_code', 'employee_name',
        'mandays', 'manday', 'qty', 'target_qty', 'flexrate',
        'start_time', 'end_time', 'duration', 'description', 'customer_code',
        'is_planned', 'is_approved', 'approved_by', 'approved_by_name', 'approved_at',
        'is_closed', 'integration_status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'workdone_date' => 'date',
        'mandays'    => 'float', 'manday' => 'float', 'qty' => 'float',
        'target_qty' => 'float', 'flexrate' => 'float', 'duration' => 'float',
        'is_planned'  => 'boolean',
        'is_approved' => 'boolean',
        'is_closed'   => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkdoneMaterial::class, 'workdone_id', 'id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeUnplanned($q) { return $q->where('is_planned', false); }
    public function scopePlanned($q)   { return $q->where('is_planned', true); }
    public function scopePending($q)   { return $q->whereNull('approved_at'); }
    public function scopeApproved($q)  { return $q->where('is_approved', true)->whereNotNull('approved_at'); }
    public function scopeRejected($q)  { return $q->where('is_approved', false)->whereNotNull('approved_at'); }
    public function scopeForDate($q, string $date) { return $q->where('workdone_date', $date); }
    public function scopeInDivisions($q, ?array $divisions)
    {
        return is_null($divisions) ? $q : $q->whereIn('division_code', $divisions);
    }

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
