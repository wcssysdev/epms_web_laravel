<?php

namespace App\Models\Master\Hse;

use Illuminate\Database\Eloquent\Model;

/**
 * HSE master: Course (portal-synced, read-only).
 *
 * Global / non-tenant — NO company_id, NO HasCompanyScope.
 * Natural key: varchar `course_no_epms`
 * → $incrementing = false, $keyType = 'string'.
 * Custom-named audit columns → $timestamps = false.
 * Wide table including portal sync columns (course_sync_status,
 * course_hse_portal_id).
 */
class Course extends Model
{
    protected $table = 'm_course';

    protected $primaryKey = 'course_no_epms';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'course_no_epms',
        'course_no',
        'course_estate_code',
        'course_name',
        'course_desc',
        'course_category',
        'course_validity_from',
        'course_validity_to',
        'course_trainer_name',
        'course_contact_detail',
        'course_duration',
        'course_type',
        'course_is_approved',
        'course_approved_timestamp',
        'course_approved_by',
        'course_approved_by_designation',
        'course_created_timestamp',
        'course_created_by',
        'course_updated_timestamp',
        'course_updated_by',
        'course_submitter_estate_code',
        'course_submitted_timestamp',
        'course_sync_status',
        'course_sync_timestamp',
        'course_hse_portal_id',
    ];

    protected $casts = [
        'course_validity_from' => 'date',
        'course_validity_to' => 'date',
        'course_duration' => 'float',
        'course_is_approved' => 'integer',
        'course_approved_timestamp' => 'datetime',
        'course_created_timestamp' => 'datetime',
        'course_updated_timestamp' => 'datetime',
        'course_submitted_timestamp' => 'datetime',
        'course_sync_timestamp' => 'datetime',
    ];
}
