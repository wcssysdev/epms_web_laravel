<?php

namespace App\Models\Transaction;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use App\Models\Global\Company;
use App\Models\Global\Role;
use App\Models\Global\CompanyConfig;

class User extends Authenticatable
{
    protected $table = 'tc_user';

    protected $fillable = [
        'username',
        'email',
        'password',
        'user_name',
        'user_employee_code',
        'user_internal_employee_code',
        'user_token',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = ['password', 'user_token'];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function access(): HasOne
    {
        return $this->hasOne(UserAccess::class, 'user_id');
    }

    /** Additive multi-scope grants (multi-estate / multi-country roles). */
    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class, 'user_id');
    }

    /** IDs of estates this user is explicitly scoped to (multi-estate roles). */
    public function scopedEstateIds(): array
    {
        return $this->scopes
            ->where('scope_type', UserScope::TYPE_ESTATE)
            ->where('is_active', true)
            ->pluck('scope_id')
            ->all();
    }

    /** IDs of countries this user is explicitly scoped to (multi-country roles). */
    public function scopedCountryIds(): array
    {
        return $this->scopes
            ->where('scope_type', UserScope::TYPE_COUNTRY)
            ->where('is_active', true)
            ->pluck('scope_id')
            ->all();
    }

    // ── Computed Attributes ───────────────────────────────────────────────────
    public function getCompanyIdAttribute(): ?int
    {
        return $this->access?->company_id;
    }

    public function getCountryIdAttribute(): ?int
    {
        return $this->access?->country_id;
    }

    public function getRoleLevelAttribute(): int
    {
        return $this->access?->role?->level ?? 99;
    }

    public function getRoleNameAttribute(): string
    {
        return $this->access?->role?->role_name ?? 'Unknown';
    }

    public function getRoleCodeAttribute(): string
    {
        return $this->access?->role?->role_code ?? '';
    }

    public function getCompanyNameAttribute(): string
    {
        return $this->access?->company?->company_name ?? '';
    }

    public function getCompanyCodeAttribute(): string
    {
        return $this->access?->company?->company_code ?? '';
    }

    public function getEstateNameAttribute(): string
    {
        return $this->companyConfig?->estate_name ?? '';
    }

    public function getEstateCodeAttribute(): string
    {
        return $this->companyConfig?->estate_code ?? '';
    }

    public function getSapClientAttribute(): string
    {
        return $this->companyConfig?->sap_client ?? '000';
    }

    public function getCompanyConfigAttribute(): ?CompanyConfig
    {
        return $this->access?->company?->config;
    }

    // ── Permission Checks ─────────────────────────────────────────────────────
    public function isSuperAdmin(): bool
    {
        return $this->access?->isSuperAdmin() ?? false;
    }

    public function isCountryAdmin(): bool
    {
        return $this->access?->isCountryAdmin() ?? false;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role_level === Role::COMPANY_ADMIN;
    }

    public function hasRoleLevel(int $level): bool
    {
        return $this->role_level <= $level;
    }

    public function canAccessCompany(int $companyId): bool
    {
        if ($this->isSuperAdmin()) return true;
        if ($this->isCountryAdmin()) {
            $company = Company::find($companyId);
            return $company?->country_id === $this->country_id;
        }
        return $this->company_id === $companyId;
    }

    // ── Auth Helpers ──────────────────────────────────────────────────────────
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function clearToken(): void
    {
        $this->update(['user_token' => null]);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope users visible to the given actor (respect company scope).
     */
    public function scopeVisibleTo($query, User $actor)
    {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isCountryAdmin()) {
            // Show users in all companies within this country
            $companyIds = Company::where('country_id', $actor->country_id)->pluck('id');
            return $query->whereHas('access', fn($q) =>
                $q->whereIn('company_id', $companyIds)
            );
        }

        // Company Admin — only own company
        return $query->whereHas('access', fn($q) =>
            $q->where('company_id', $actor->company_id)
        );
    }
}
