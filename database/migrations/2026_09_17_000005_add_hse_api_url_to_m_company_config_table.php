<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add the HSE Portal base URL column (hse_api_url) used by the HSE integration.
 *
 * Schema authority: C:\xampp\htdocs\epms24\CR_HSE_2026.txt.
 *
 * CI3 → Laravel redesign note:
 *   The legacy CI3 app stored global settings in a single-row `m_config` table.
 *   The Laravel target app redesigned this into `m_company_config` (one row per
 *   company), modeled by App\Models\Global\CompanyConfig
 *   (protected $table = 'm_company_config'). The CR references `m_config`, but the
 *   correct target here is `m_company_config`.
 *
 * This mirrors the existing SAP integration pattern: SapService reads
 * `$config->sap_api_url` from CompanyConfig, so the HSE Portal base URL is added
 * as `hse_api_url` on the same table and is accessed the same way
 * (`$config->hse_api_url`). Existing rows are seeded with the default HSE API URL
 * (mirroring the CR_HSE_2026.txt UPDATE statement).
 */
return new class extends Migration
{
    /**
     * Run outside a transaction. On the ODBC SQL Server connection, DDL
     * (ALTER TABLE) implicitly commits, so wrapping the migration in a
     * transaction leaves no active transaction for the error handler's
     * ROLLBACK and raises SQLSTATE 25000 (3903) that masks the real result.
     */
    public $withinTransaction = false;

    private const DEFAULT_HSE_API_URL =
        'http://ec2-15-232-111-191.ap-southeast-3.compute.amazonaws.com/hse-api/api';

    public function up(): void
    {
        if (! Schema::hasTable('m_company_config')) {
            return;
        }

        Schema::table('m_company_config', function (Blueprint $table) {
            if (! Schema::hasColumn('m_company_config', 'hse_api_url')) {
                $table->string('hse_api_url', 500)->nullable();
            }
        });

        // Seed existing rows with the default HSE Portal base URL
        // (mirror the CR_HSE_2026.txt UPDATE).
        if (Schema::hasColumn('m_company_config', 'hse_api_url')) {
            DB::table('m_company_config')
                ->where(function ($query) {
                    $query->whereNull('hse_api_url')
                        ->orWhere('hse_api_url', '');
                })
                ->update(['hse_api_url' => self::DEFAULT_HSE_API_URL]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('m_company_config')) {
            return;
        }

        Schema::table('m_company_config', function (Blueprint $table) {
            if (Schema::hasColumn('m_company_config', 'hse_api_url')) {
                $table->dropColumn('hse_api_url');
            }
        });
    }
};
