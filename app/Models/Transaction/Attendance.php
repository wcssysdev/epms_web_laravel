<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Attendance record (read-only monitoring for managers).
 * No division_code column — attendance is employee/gang-based.
 */
class Attendance extends Model
{
    use HasCompanyScope;

    protected $table = 't_attendance';

    protected $fillable = [
        'company_id', 'attendance_date',
        'mandor_employee_code', 'mandor_employee_name',
        'employee_code', 'employee_name',
        'attendance_code', 'attendance_desc', 'work_status', 'gang_allotment_code',
        'is_closed', 'integration_status', 'remark', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'work_status'     => 'integer',
        'is_closed'       => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeBetween($q, string $from, string $to)
    {
        return $q->whereBetween('attendance_date', [$from, $to]);
    }
}
