<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Email Receiver (full CRUD, EPMS-authored).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Sequence-keyed: integer `id` with a DB sequence default
 * → default auto-increment ($incrementing = true), $keyType = 'int'.
 * Custom-named audit columns (created_date / updated_date, no deleted_*)
 * → $timestamps = false.
 */
class EmailReceiver extends Model
{
    protected $table = 'm_hse_email_receiver';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'receiver_name',
        'receiver_email',
        'receiver_level',
        'receiver_department',
        'receiver_notification_type',
        'receiver_is_active',
        'created_by',
        'created_date',
        'updated_by',
        'updated_date',
    ];

    protected $casts = [
        'id' => 'integer',
        'receiver_is_active' => 'integer',
        'created_date' => 'datetime',
        'updated_date' => 'datetime',
    ];
}
