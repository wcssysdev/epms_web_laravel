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
        'system_is_palm'              => 'boolean',
        'system_is_coconut'           => 'boolean',
        'system_is_rubber'            => 'boolean',
        'system_is_durian'            => 'boolean',
        'have_internet_connection'    => 'boolean',
        'fdn_oph'                     => 'boolean',
        'is_fixed_platform'           => 'boolean',
        'is_lock_system'              => 'boolean',
        'allowed_attendance_codes'    => 'array',
        'additional_settings'         => 'array',
        'cutter_distribution_value'   => 'float',
        'carrier_distribution_value'  => 'float',
        'cutter_lf_distribution_value'  => 'float',
        'carrier_lf_distribution_value' => 'float',
        'daily_overtime_max_limit'    => 'integer',
        'max_oph_restan'              => 'integer',
        'integration_type'            => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getSapClientAttribute($value): string
    {
        return $value ?? '000';
    }

    public function getIntegrationTypeLabelAttribute(): string
    {
        return match ((int) $this->integration_type) {
            1 => 'SAP',
            2 => 'Pinfosys',
            default => 'Unknown',
        };
    }

    // ── Additional Settings Helpers ───────────────────────────────────────────
    public function getAdditionalSetting(string $key, mixed $default = null): mixed
    {
        return ($this->additional_settings ?? [])[$key] ?? $default;
    }

    public function setAdditionalSetting(string $key, mixed $value): void
    {
        $settings = $this->additional_settings ?? [];
        $settings[$key] = $value;
        $this->additional_settings = $settings;
    }

    // ── System Type ───────────────────────────────────────────────────────────
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

    public function getEnabledSystemTypes(): array
    {
        $types = [];
        if ($this->system_is_palm)    $types[] = 'Palm';
        if ($this->system_is_coconut) $types[] = 'Coconut';
        if ($this->system_is_rubber)  $types[] = 'Rubber';
        if ($this->system_is_durian)  $types[] = 'Durian';
        return $types;
    }

    // ── Distribution Validation ───────────────────────────────────────────────
    public function isCutterCarrierValid(): bool
    {
        return ($this->cutter_distribution_value + $this->carrier_distribution_value) == 100;
    }

    // ── SAP Connection Test ───────────────────────────────────────────────────
    public function testSapConnection(): array
    {
        if (empty($this->sap_api_url)) {
            return ['success' => false, 'message' => 'SAP API URL is not configured.'];
        }

        try {
            $ch = curl_init($this->sap_api_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERPWD        => $this->sap_user_id . ':' . $this->sap_password,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'message' => "Connection failed: {$error}"];
            }

            if ($httpCode >= 200 && $httpCode < 500) {
                return ['success' => true,  'message' => "Connected successfully (HTTP {$httpCode})"];
            }

            return ['success' => false, 'message' => "Server returned HTTP {$httpCode}"];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
