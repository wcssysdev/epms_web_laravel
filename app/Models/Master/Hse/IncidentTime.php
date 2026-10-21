<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Incident Time (CRUD + CSV; no portal sync).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Natural key: varchar `incident_time_name`
 * → $incrementing = false, $keyType = 'string'.
 * Custom-named audit columns → $timestamps = false.
 */
class IncidentTime extends Model
{
    protected $table = 'm_incident_time';

    protected $primaryKey = 'incident_time_name';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'incident_time_name',
        'incident_time_created_timestamp',
        'incident_time_created_by',
        'incident_time_updated_timestamp',
        'incident_time_updated_by',
    ];

    protected $casts = [
        'incident_time_created_timestamp' => 'datetime',
        'incident_time_updated_timestamp' => 'datetime',
    ];
}
