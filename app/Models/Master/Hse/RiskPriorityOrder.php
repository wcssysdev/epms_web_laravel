<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Risk Priority Order (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Natural key: varchar `risk_acceptability_name`
 * → $incrementing = false, $keyType = 'string'.
 * Custom-named audit columns → $timestamps = false.
 */
class RiskPriorityOrder extends Model
{
    protected $table = 'm_risk_priority_order';

    protected $primaryKey = 'risk_acceptability_name';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'risk_acceptability_name',
        'risk_order',
        'risk_range_lower',
        'risk_range_upper',
        'risk_created_timestamp',
        'risk_created_by',
        'risk_updated_timestamp',
        'risk_updated_by',
    ];

    protected $casts = [
        'risk_order' => 'integer',
        'risk_range_lower' => 'integer',
        'risk_range_upper' => 'integer',
        'risk_created_timestamp' => 'datetime',
        'risk_updated_timestamp' => 'datetime',
    ];
}
