<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * General Worker Assignment — assigns a worker to an activity for a date.
 * Cost object depends on the activity (block for field activities, order
 * number for order-based ones). Operational entry by Estate Staff (CI3 role 4).
 */
class GeneralWorkerAssignment extends Model
{
    use HasCompanyScope;

    protected $table = 't_general_worker_assignment';

    protected $fillable = [
        'company_id', 'assignment_date', 'estate_code', 'division_code',
        'mandor_employee_code', 'mandor_employee_name',
        'worker_employee_code', 'worker_employee_name',
        'activity_code', 'activity_name', 'block_code', 'order_number',
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
