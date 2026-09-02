<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoconutOphDetail extends Model
{
    protected $table = 't_coconut_oph_detail';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'coconut_oph_id', 'material_code', 'material_name',
        'customer_nut_qty', 'is_locked', 'is_deleted', 'integration_status', 'remark',
    ];

    protected $casts = [
        'customer_nut_qty' => 'float',
        'is_locked'        => 'boolean',
        'is_deleted'       => 'boolean',
    ];

    public function coconutOph(): BelongsTo
    {
        return $this->belongsTo(CoconutOph::class, 'coconut_oph_id', 'id');
    }
}
