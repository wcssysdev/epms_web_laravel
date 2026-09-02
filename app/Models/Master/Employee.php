<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

class Employee extends Model
{
    use HasCompanyScope;

    protected $table = 'm_employee';

    protected $fillable = [
        'company_id',
        'employee_code',
        'employee_name',
        'employee_estate_code',
        'employee_division_code',
        'employee_sex',
        'employee_job_code',
        'employee_job_type',
        'employee_status',
        'employee_stats',
        'employee_profile',
        'employee_department',
        'employee_vendor',
        'is_internal_estate',
        'valid_from',
        'valid_to',
        'work_permit_exp_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_internal_estate'    => 'boolean',
        'valid_from'            => 'date',
        'valid_to'              => 'date',
        'work_permit_exp_date'  => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $today = now()->toDateString();
            $q->where('valid_from', '<=', $today)
              ->where('valid_to', '>=', $today);
        });
    }

    public function scopeByEstate($query, string $estateCode)
    {
        return $query->where('employee_estate_code', $estateCode);
    }

    public function scopeByDivision($query, string $divisionCode)
    {
        return $query->where('employee_division_code', $divisionCode);
    }

    public function scopeByJobCode($query, string $jobCode)
    {
        return $query->where('employee_job_code', $jobCode);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('employee_code', 'ilike', "%{$search}%")
              ->orWhere('employee_name', 'ilike', "%{$search}%")
              ->orWhere('employee_job_code', 'ilike', "%{$search}%");
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function isActive(): bool
    {
        $today = now()->toDateString();
        return $this->valid_from <= $today && $this->valid_to >= $today;
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->employee_code} - {$this->employee_name}";
    }
}
