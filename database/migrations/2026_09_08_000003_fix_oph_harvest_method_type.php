<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EPMS Mobile API (v1_1) — BATCH 2c fix.
 *
 * t_oph.harvest_method was defined as smallint in the redesigned Laravel schema,
 * but the harvest method indicator is a LETTER (m_harvest_method.mhm_indicator =
 * M/L/K/C/...) in CI3, and the mobile app uploads it as a string. Storing it as
 * smallint rejects the mobile value ("invalid input syntax for type smallint").
 *
 * Change the column to varchar to match the CI3 contract. Safe: no web code
 * references harvest_method and t_oph is empty. Uses raw SQL with USING so it
 * works even if rows exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('t_oph', 'harvest_method')) {
            DB::statement("ALTER TABLE t_oph ALTER COLUMN harvest_method TYPE varchar(10) USING harvest_method::varchar");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('t_oph', 'harvest_method')) {
            // Revert to smallint; non-numeric values become NULL to avoid cast errors.
            DB::statement("ALTER TABLE t_oph ALTER COLUMN harvest_method TYPE smallint USING (NULLIF(regexp_replace(harvest_method, '\\D', '', 'g'), '')::smallint)");
        }
    }
};
