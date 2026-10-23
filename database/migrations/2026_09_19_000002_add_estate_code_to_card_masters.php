<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add estate_code to mc_oph_card and mc_fdn_card so the card masters follow
 * the multi-company / multi-estate design (company_id already present; cards
 * are still filtered by the logged-in company). Nullable so existing rows
 * remain valid.
 *
 * SQL Server (odbc_sqlsrv) auto-commits DDL → disable the transaction wrapper
 * and guard with Schema::hasColumn for idempotent re-runs.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private array $tables = ['mc_oph_card', 'mc_fdn_card'];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (Schema::hasTable($tbl) && ! Schema::hasColumn($tbl, 'estate_code')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->string('estate_code', 20)->nullable()->after('company_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'estate_code')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropColumn('estate_code');
                });
            }
        }
    }
};