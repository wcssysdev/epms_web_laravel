<?php

use Illuminate\Support\Facades\Auth;

if (! function_exists('current_user')) {
    /** Get the authenticated user with relations loaded */
    function current_user(): ?\App\Models\Transaction\User
    {
        return Auth::user();
    }
}

if (! function_exists('current_company_id')) {
    function current_company_id(): ?int
    {
        return Auth::user()?->company_id ?? session('company_id');
    }
}

if (! function_exists('current_company_code')) {
    function current_company_code(): string
    {
        return Auth::user()?->company_code ?? session('company_code', '');
    }
}

if (! function_exists('current_estate_code')) {
    function current_estate_code(): string
    {
        return Auth::user()?->estateCode ?? session('estate_code', '');
    }
}

if (! function_exists('current_role_level')) {
    function current_role_level(): int
    {
        return Auth::user()?->role_level ?? 99;
    }
}

if (! function_exists('is_super_admin')) {
    function is_super_admin(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }
}

if (! function_exists('is_country_admin')) {
    function is_country_admin(): bool
    {
        return Auth::user()?->isCountryAdmin() ?? false;
    }
}

if (! function_exists('is_company_admin')) {
    function is_company_admin(): bool
    {
        return Auth::user()?->isCompanyAdmin() ?? false;
    }
}

if (! function_exists('system_locked')) {
    function system_locked(): bool
    {
        return session('is_locked', false);
    }
}

if (! function_exists('system_enabled')) {
    /** Check if a system type (palm/coconut/durian/rubber) is enabled */
    function system_enabled(string $type): bool
    {
        return Auth::user()?->companyConfig?->isSystemEnabled($type) ?? false;
    }
}

if (! function_exists('company_config')) {
    function company_config(): ?\App\Models\Global\CompanyConfig
    {
        return Auth::user()?->companyConfig;
    }
}

if (! function_exists('audit_fields')) {
    /** Return created_by/at + updated_by/at for DB insert */
    function audit_fields(): array
    {
        $name = Auth::user()?->user_name ?? 'system';
        return [
            'created_by' => $name,
            'created_at' => now(),
            'updated_by' => $name,
            'updated_at' => now(),
        ];
    }
}

if (! function_exists('audit_update_fields')) {
    /** Return updated_by/at for DB update */
    function audit_update_fields(): array
    {
        return [
            'updated_by' => Auth::user()?->user_name ?? 'system',
            'updated_at' => now(),
        ];
    }
}

if (! function_exists('format_date')) {
    /** Format date to d/m/Y */
    function format_date(?string $date): string
    {
        if (! $date) return '-';
        try {
            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }
}

if (! function_exists('format_datetime')) {
    /** Format datetime to d/m/Y H:i */
    function format_datetime(?string $datetime): string
    {
        if (! $datetime) return '-';
        try {
            return \Carbon\Carbon::parse($datetime)->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return $datetime;
        }
    }
}
