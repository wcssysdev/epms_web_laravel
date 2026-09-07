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
            $table = $builder->getModel()->getTable();
            $builder->where($table . '.company_id', $access->company_id);

            // Multi-estate roles (e.g. Plantation Controller) are further
            // restricted to the estates assigned via tc_user_scope — but only
            // when the table actually carries an estate_code column.
            $estateCodes = static::assignedEstateCodes($user);
            if ($estateCodes !== null && \Illuminate\Support\Facades\Schema::hasColumn($table, 'estate_code')) {
                if ($estateCodes === []) {
                    $builder->whereRaw('1 = 0'); // assigned to no estates → see nothing
                } else {
                    $builder->whereIn($table . '.estate_code', $estateCodes);
                }
            }
        });
    }

    /**
     * Resolve the estate_code values a user is restricted to via tc_user_scope.
     * Returns null when the user has no estate-scope grants (no restriction),
     * or an array of estate codes (possibly empty) when they do.
     *
     * Cached per-request keyed by user id to avoid repeat lookups across models.
     */
    protected static function assignedEstateCodes($user): ?array
    {
        static $cache = [];
        $uid = $user->id ?? 0;
        if (array_key_exists($uid, $cache)) {
            return $cache[$uid];
        }

        $estateIds = method_exists($user, 'scopedEstateIds') ? $user->scopedEstateIds() : [];
        if (empty($estateIds)) {
            return $cache[$uid] = null; // no estate-scope grants → no restriction
        }

        $codes = \Illuminate\Support\Facades\DB::table('m_estate')
            ->whereIn('id', $estateIds)
            ->pluck('estate_code')
            ->all();

        return $cache[$uid] = $codes;
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
