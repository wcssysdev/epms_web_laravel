<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkdoneMaterial extends Model
{
    protected $table = 't_workdone_material';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'workdone_id', 'material_code', 'material_name', 'qty',
    ];

    protected $casts = ['qty' => 'float'];

    public function workdone(): BelongsTo
    {
        return $this->belongsTo(Workdone::class, 'workdone_id', 'id');
    }
}
