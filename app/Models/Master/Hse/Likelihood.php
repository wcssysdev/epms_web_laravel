<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Likelihood (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Sequence-keyed: integer `lhood_id` with a DB identity default → default
 * auto-increment behaviour ($incrementing = true), $keyType = 'int'.
 * Custom-named audit columns → $timestamps = false.
 * Carries an is_active flag.
 */
class Likelihood extends Model
{
    protected $table = 'm_likelihood';

    protected $primaryKey = 'lhood_id';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'lhood_name',
        'lhood_description',
        'lhood_factors',
        'lhood_justification',
        'lhood_code',
        'lhood_rating',
        'is_active',
        'lhood_created_timestamp',
        'lhood_created_by',
        'lhood_updated_timestamp',
        'lhood_updated_by',
    ];

    protected $casts = [
        'lhood_id' => 'integer',
        'lhood_rating' => 'integer',
        'is_active' => 'boolean',
        'lhood_created_timestamp' => 'datetime',
        'lhood_updated_timestamp' => 'datetime',
    ];
}
