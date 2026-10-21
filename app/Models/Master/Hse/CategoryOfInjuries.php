<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Category of Injuries (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 *
 * Identity-keyed: the integer `id` is assigned MANUALLY during the portal
 * Replace step (COALESCE(MAX(id),0)+1), not by a DB sequence, so
 * $incrementing = false with $keyType = 'int'.
 *
 * Audit columns are custom-named (created_date, not created_at) → $timestamps = false.
 */
class CategoryOfInjuries extends Model
{
    protected $table = 'm_category_of_injuries';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'category_injuries_code',
        'category_injuries',
        'injuries_type',
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
