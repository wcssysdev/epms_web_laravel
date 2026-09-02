<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Global\Company;
use App\Models\Global\Country;
use App\Models\Global\Role;

class UserAccess extends Model
{
    protected $table = 'tc_user_access';

    protected $fillable = [
        'user_id',
        'role_id',
        'country_id',
        'company_id',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function isSuperAdmin(): bool
    {
        return is_null($this->country_id) && is_null($this->company_id);
    }

    public function isCountryAdmin(): bool
    {
        return !is_null($this->country_id) && is_null($this->company_id);
    }

    public function isCompanyLevel(): bool
    {
        return !is_null($this->company_id);
    }
}
