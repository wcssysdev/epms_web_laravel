<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OphPerson extends Model
{
    protected $table = 't_oph_persons';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'oph_id', 'employee_code', 'employee_name',
        'percentage', 'total_bunches', 'estimate_tonnage', 'person_type', 'employee_type',
    ];

    protected $casts = [
        'percentage'       => 'float',
        'total_bunches'    => 'float',
        'estimate_tonnage' => 'float',
        'person_type'      => 'integer',
        'employee_type'    => 'integer',
    ];

    public function oph(): BelongsTo
    {
        return $this->belongsTo(Oph::class, 'oph_id', 'id');
    }
}
