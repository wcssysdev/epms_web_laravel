<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Apply automatic company/country scope to all models.
 *
 * Usage: add `use HasCompanyScope;` to any Eloquent model
 * that has a company_id column (Kategori B & C tables).
 *
 * Scope logic:
 *   Super Admin  (level 10) → no filter
 *   Country Admin (level 20) → filter by country via company join
 *   Company-level (level 30+) → filter by company_id
 */
trait HasCompanyScope
{
    public static function bootHasCompanyScope(): void
    {
        static::addGlobalScope('company_scope', function (Builder $builder) {
            // Only apply when running in HTTP context (not in CLI/tinker/migrations)
            if (app()->runningInConsole()) {
                return;
            }

            $user = auth()->user();
            if (! $user) {
                return;
            }

            $access = $user->access;
            if (! $access) {
                return;
            }

            // Super Admin → bypass
            if ($access->isSuperAdmin()) {
                return;
            }

            // Country Admin → filter all companies within this country
            if ($access->isCountryAdmin()) {
                $builder->whereHas('company', function (Builder $q) use ($access) {
                    $q->where('country_id', $access->country_id);
                });
                return;
            }

            // Company-level → strict single company
            $builder->where(
                $builder->getModel()->getTable() . '.company_id',
                $access->company_id
            );
        });
    }

    /**
     * Bypass global scope for a specific query.
     * Usage: Model::withoutCompanyScope()->get()
     */
    public static function withoutCompanyScope(): Builder
    {
        return static::withoutGlobalScope('company_scope');
    }
}
