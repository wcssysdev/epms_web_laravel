<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiPlanDetail extends Model
{
    protected $table = 'tr_gi_plan_detail';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'gi_plan_id', 'material_code', 'material_name',
        'qty', 'uom', 'cost_center', 'wbs_code', 'order_number',
    ];

    protected $casts = ['qty' => 'float'];

    public function giPlan(): BelongsTo
    {
        return $this->belongsTo(GiPlan::class, 'gi_plan_id', 'id');
    }
}
