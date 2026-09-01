<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'm_roles';

    protected $fillable = [
        'role_code',
        'role_name',
        'level',
        'required_system_type',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // ── Level constants ───────────────────────────────────────────────
    const SUPER_ADMIN    = 10;
    const COUNTRY_ADMIN  = 20;
    const COMPANY_ADMIN  = 30;
    const ESTATE_MANAGER = 40;
    const ASST_MANAGER   = 50;
    const STAFF          = 60;
    const OPERATIONAL    = 70;

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter roles available for a given company config.
     * Hides palm/coconut/durian roles if that system type is not enabled.
     */
    public function scopeAvailableFor($query, CompanyConfig $config)
    {
        return $query->where(function ($q) use ($config) {
            $q->whereNull('required_system_type')
              ->orWhere(function ($q2) use ($config) {
                  $q2->where('required_system_type', 'palm')
                     ->where($config->system_is_palm ? '1' : '0', '1');
              })
              ->orWhere(function ($q2) use ($config) {
                  $q2->where('required_system_type', 'coconut')
                     ->where($config->system_is_coconut ? '1' : '0', '1');
              })
              ->orWhere(function ($q2) use ($config) {
                  $q2->where('required_system_type', 'durian')
                     ->where($config->system_is_durian ? '1' : '0', '1');
              });
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────
    public function isSuperAdmin(): bool   { return $this->level === self::SUPER_ADMIN; }
    public function isCountryAdmin(): bool { return $this->level === self::COUNTRY_ADMIN; }
    public function isCompanyAdmin(): bool { return $this->level === self::COMPANY_ADMIN; }
    public function isAboveCompany(): bool { return $this->level <= self::COMPANY_ADMIN; }
}
