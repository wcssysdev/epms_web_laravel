<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Traits\HasCompanyScope;

/**
 * Mill Grader OPH (t_mill_grader_oph) — read-only monitoring. Data captured
 * by the mill grader (mobile); the web view is monitoring only.
 */
class MillGraderOph extends Model
{
    use HasCompanyScope;

    protected $table = 't_mill_grader_oph';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'company_id', 'estate_code', 'plant_code', 'division_code',
        'block_code', 'tph_code', 'grader_employee_code', 'grader_employee_name',
        'is_approved', 'approved_by', 'approved_at', 'is_closed',
        'integration_status', 'remark', 'request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_closed'   => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
