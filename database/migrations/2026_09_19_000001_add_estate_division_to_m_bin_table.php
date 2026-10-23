<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add estate_code + division_code to m_bin so the BIN master follows the
 * multi-company / multi-estate design (company_id already present; BIN is
 * still filtered by the logged-in company). Columns are nullable so existing
 * rows remain valid.
 *
 * SQL Server (odbc_sqlsrv) auto-commits DDL, so disable the migration
 * transaction wrapper and guard each column with Schema::hasColumn to stay
 * idempotent / re-runnable.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('m_bin', 'estate_code')) {
            Schema::table('m_bin', function (Blueprint $table) {
                $table->string('estate_code', 20)->nullable()->after('company_id');
            });
        }

        if (! Schema::hasColumn('m_bin', 'division_code')) {
            Schema::table('m_bin', function (Blueprint $table) {
                $table->string('division_code', 50)->nullable()->after('estate_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('m_bin', 'division_code')) {
            Schema::table('m_bin', function (Blueprint $table) {
                $table->dropColumn('division_code');
            });
        }

        if (Schema::hasColumn('m_bin', 'estate_code')) {
            Schema::table('m_bin', function (Blueprint $table) {
                $table->dropColumn('estate_code');
            });
        }
    }
};