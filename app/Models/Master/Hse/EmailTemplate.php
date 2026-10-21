<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Email Template (full CRUD, EPMS-authored).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Sequence-keyed: integer `id` with a DB sequence default
 * → default auto-increment ($incrementing = true), $keyType = 'int'.
 * UNIQUE template_code enforced at the DB level.
 * template_body holds CKEditor HTML.
 * Custom-named audit columns (created_date / updated_date, no deleted_*)
 * → $timestamps = false.
 */
class EmailTemplate extends Model
{
    protected $table = 'm_hse_email_template';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'template_code',
        'template_name',
        'template_subject',
        'template_body',
        'template_is_active',
        'created_by',
        'created_date',
        'updated_by',
        'updated_date',
    ];

    protected $casts = [
        'id' => 'integer',
        'template_is_active' => 'integer',
        'created_date' => 'datetime',
        'updated_date' => 'datetime',
    ];
}
