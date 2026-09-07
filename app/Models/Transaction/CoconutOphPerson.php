<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coconut OPH harvester (t_coconut_oph_persons) — employee who harvested,
 * with an activity type.
 */
class CoconutOphPerson extends Model
{
    protected $table = 't_coconut_oph_persons';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'coconut_oph_id', 'employee_code', 'employee_name',
        'activity_type', 'integration_status', 'remark',
    ];

    public function coconutOph(): BelongsTo
    {
        return $this->belongsTo(CoconutOph::class, 'coconut_oph_id', 'id');
    }
}
