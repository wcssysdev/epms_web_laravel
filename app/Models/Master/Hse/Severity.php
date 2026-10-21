<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Severity (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Sequence-keyed: integer `id` with a DB sequence default
 * (m_severity_id_seq) → default auto-increment ($incrementing = true),
 * $keyType = 'int'.
 * Custom-named audit columns → $timestamps = false.
 */
class Severity extends Model
{
    protected $table = 'm_severity';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'severity_code',
        'severity_name',
        'severity_rating',
        'description',
        'severity_created_timestamp',
        'severity_created_by',
        'severity_updated_timestamp',
        'severity_updated_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'severity_rating' => 'integer',
        'severity_created_timestamp' => 'datetime',
        'severity_updated_timestamp' => 'datetime',
    ];
}
