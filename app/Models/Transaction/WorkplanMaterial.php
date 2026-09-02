<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkplanMaterial extends Model
{
    protected $table = 't_workplan_material';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'workplan_id',
        'material_code',
        'material_name',
        'qty',
    ];

    protected $casts = [
        'qty' => 'float',
    ];

    public function workplan(): BelongsTo
    {
        return $this->belongsTo(Workplan::class, 'workplan_id', 'id');
    }
}
