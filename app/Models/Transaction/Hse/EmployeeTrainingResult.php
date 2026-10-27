<?php

namespace App\Models\Transaction\Hse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\Hse\Course;
use App\Models\Master\Employee;

/**
 * Employee Training Result Model
 * Maps to t_employee_training_result
 */
class EmployeeTrainingResult extends Model
{
    protected $table = 't_employee_training_result';

    protected $primaryKey = 'result_id';

    public $timestamps = false;

    protected $fillable = [
        'training_schedule_id',
        'employee_code',
        'employee_name',
        'course_no',
        'training_date',
        'training_result_type',
        'training_result_score',
        'assessment_method',
        'assessment_by',
        'assessment_notes',
        'attendance_status',
        'attendance_timestamp',
        'result_created_timestamp',
        'result_created_by',
        'result_updated_timestamp',
        'result_updated_by',
        'sync_status',
        'sync_timestamp',
        'hse_portal_ref',
        'attendance_notes',
    ];

    protected $casts = [
        'training_date' => 'date',
        'training_result_score' => 'float',
        'attendance_timestamp' => 'datetime',
        'result_created_timestamp' => 'datetime',
        'result_updated_timestamp' => 'datetime',
        'sync_timestamp' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_no', 'course_no_epms');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'employee_code');
    }
}
