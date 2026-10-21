<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: First Aid Kit (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * (estate_code / division_code are plain reference columns, not tenant scope.)
 * Identity-keyed: integer `id` assigned manually during Replace
 * (COALESCE(MAX(id),0)+1) → $incrementing = false, $keyType = 'int'.
 * Custom-named audit columns → $timestamps = false.
 * Carries an is_active flag.
 */
class FirstAidKit extends Model
{
    protected $table = 'm_first_aid_kit';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'kit_code',
        'kit_name',
        'uom',
        'estate_code',
        'division_code',
        'is_active',
        'created_by',
        'created_date',
        'updated_by',
        'updated_date',
        'deleted_by',
        'deleted_date',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'created_date' => 'datetime',
        'updated_date' => 'datetime',
        'deleted_date' => 'datetime',
    ];
}
