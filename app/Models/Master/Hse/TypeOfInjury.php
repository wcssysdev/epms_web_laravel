<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Type of Injury (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Identity-keyed: integer `id` assigned manually during Replace
 * (COALESCE(MAX(id),0)+1) → $incrementing = false, $keyType = 'int'.
 * Custom-named audit columns → $timestamps = false.
 */
class TypeOfInjury extends Model
{
    protected $table = 'm_type_of_injury';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'type_injury_code',
        'injury_name',
        'afr_code',
        'afr_jkkp_name',
        'benchmark_esg_name',
        'created_by',
        'created_date',
        'updated_by',
        'updated_date',
        'deleted_by',
        'deleted_date',
    ];

    protected $casts = [
        'id' => 'integer',
        'created_date' => 'datetime',
        'updated_date' => 'datetime',
        'deleted_date' => 'datetime',
    ];
}
