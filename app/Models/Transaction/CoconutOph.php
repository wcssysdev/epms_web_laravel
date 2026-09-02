<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Coconut OPH / Harvesting Chit. Approved by Estate Manager (role 2) or
 * Assistant Manager (role 3, division-scoped) via the Harvesting Chit (Coconut) flow.
 */
class CoconutOph extends Model
{
    use HasCompanyScope;

    protected $table = 't_coconut_oph';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'plant_code', 'estate_code', 'division_code', 'block_code',
        'tph_code', 'oph_card_id', 'gang_code', 'gang_name',
        'checker_employee_code', 'checker_employee_name', 'notes', 'lat', 'long', 'photo',
        'nuts_total',
        'is_planned', 'is_approved', 'approved_by', 'approved_by_name', 'approved_at',
        'is_closed', 'is_deleted', 'integration_status', 'remark', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'nuts_total'  => 'integer',
        'is_planned'  => 'boolean',
        'is_approved' => 'boolean',
        'is_closed'   => 'boolean',
        'is_deleted'  => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CoconutOphDetail::class, 'coconut_oph_id', 'id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActual($q)   { return $q->where('is_planned', false)->where('is_deleted', false); }
    public function scopePending($q)  { return $q->whereNull('approved_at'); }
    public function scopeApproved($q) { return $q->where('is_approved', true)->whereNotNull('approved_at'); }
    public function scopeRejected($q) { return $q->where('is_approved', false)->whereNotNull('approved_at'); }
    public function scopeForDate($q, string $date) { return $q->whereDate('created_at', $date); }
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
