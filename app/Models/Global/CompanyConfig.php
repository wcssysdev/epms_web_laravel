<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyConfig extends Model
{
    protected $table = 'm_company_config';

    protected $fillable = [
        'company_id',
        'profile_code', 'profile_name',
        'estate_code',  'estate_name', 'plant_code',
        'sap_client',
        'system_is_palm', 'system_is_coconut',
        'system_is_rubber', 'system_is_durian',
        'integration_type', 'have_internet_connection',
        'sap_api_url', 'sap_user_id', 'sap_password',
        'cutter_distribution_value', 'carrier_distribution_value',
        'cutter_lf_distribution_value', 'carrier_lf_distribution_value',
        'attendance_default_value', 'attendance_normal_default_value',
        'allowed_attendance_codes',
        'daily_overtime_max_limit', 'max_oph_restan',
        'fdn_oph', 'is_fixed_platform', 'is_lock_system',
        'additional_settings',
    ];

    protected $casts = [
        'system_is_palm'             => 'boolean',
        'system_is_coconut'          => 'boolean',
        'system_is_rubber'           => 'boolean',
        'system_is_durian'           => 'boolean',
        'have_internet_connection'   => 'boolean',
        'fdn_oph'                    => 'boolean',
        'is_fixed_platform'          => 'boolean',
        'is_lock_system'             => 'boolean',
        'allowed_attendance_codes'   => 'array',
        'additional_settings'        => 'array',
        'cutter_distribution_value'  => 'float',
        'carrier_distribution_value' => 'float',
    ];

    // ── Relationships ────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────
    public function getAdditionalSetting(string $key, mixed $default = null): mixed
    {
        return $this->additional_settings[$key] ?? $default;
    }

    public function isSystemEnabled(string $type): bool
    {
        return match ($type) {
            'palm'    => (bool) $this->system_is_palm,
            'coconut' => (bool) $this->system_is_coconut,
            'rubber'  => (bool) $this->system_is_rubber,
            'durian'  => (bool) $this->system_is_durian,
            default   => false,
        };
    }

    public function getSapClientAttribute($value): string
    {
        return $value ?? '000';
    }
}
