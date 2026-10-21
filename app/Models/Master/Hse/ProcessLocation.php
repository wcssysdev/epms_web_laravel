<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Process Location (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Natural key: varchar `ploc_id` → $incrementing = false, $keyType = 'string'.
 * Custom-named audit columns → $timestamps = false.
 */
class ProcessLocation extends Model
{
    protected $table = 'm_process_location';

    protected $primaryKey = 'ploc_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'ploc_id',
        'ploc_desc',
        'ploc_status',
        'ploc_rev_no',
        'ploc_created_timestamp',
        'ploc_created_by',
        'ploc_updated_timestamp',
        'ploc_updated_by',
    ];

    protected $casts = [
        'ploc_created_timestamp' => 'datetime',
        'ploc_updated_timestamp' => 'datetime',
    ];
}
