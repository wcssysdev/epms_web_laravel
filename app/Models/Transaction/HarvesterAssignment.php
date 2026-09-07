<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Harvester Assignment — maps a harvester employee to a block/TPH for a date.
 * Operational entry by Estate Staff (CI3 role 4). Company + estate scoped.
 */
class HarvesterAssignment extends Model
{
    use HasCompanyScope;

    protected $table = 't_harvester_assignment';

    protected $fillable = [
        'company_id', 'assignment_date', 'estate_code', 'division_code',
        'mandor_employee_code', 'mandor_employee_name',
        'harvester_employee_code', 'harvester_employee_name',
        'block_code', 'tph_code',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'assignment_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeForDate($q, string $date)
    {
        return $q->where('assignment_date', $date);
    }

    public function scopeInDivisions($q, ?array $divisions)
    {
        return is_null($divisions) ? $q : $q->whereIn('division_code', $divisions);
    }
}
