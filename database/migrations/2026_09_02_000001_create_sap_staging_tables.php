<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAP master-data staging tables (ZEPMS_*_OUT).
 *
 * These mirror the CI3/CI4 SAP staging tables: raw SAP field names as columns.
 * Extended for the multi-tenant Laravel build with:
 *   - company_code  : which EPMS company the pull belongs to (EPMS uses company_code as tenant key)
 *   - country_code  : default 'MY' (resolved from Estate Settings at sync time)
 *   - country_no    : default '1'  (country prefix, resolved from Estate Settings)
 *
 * Flow:
 *   Step 1 "Get from SAP"      → truncate+insert this staging table (scoped per company_code)
 *   Step 2 "Refresh Master"    → map staging → m_* master table
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ZEPMS_EM_ESTATE_OUT ──────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_ESTATE_OUT')) {
            Schema::create('ZEPMS_EM_ESTATE_OUT', function (Blueprint $t) {
                $t->bigIncrements('estate_id');
                $this->tenantColumns($t);
                $t->string('BUKRS')->nullable();  // company code
                $t->string('ESTNR')->nullable();  // estate code
                $t->string('NAME1')->nullable();  // estate name
                $t->string('WERKS')->nullable();  // plant code
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_DIVISION_OUT ────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_DIVISION_OUT')) {
            Schema::create('ZEPMS_EM_DIVISION_OUT', function (Blueprint $t) {
                $t->bigIncrements('division_id');
                $this->tenantColumns($t);
                $t->string('BUKRS')->nullable();  // company code
                $t->string('ESTNR')->nullable();  // estate code
                $t->string('SPART')->nullable();  // division code
                $t->string('VTEXT')->nullable();  // division name
                $t->string('KDATB')->nullable();  // valid from
                $t->string('KDATE')->nullable();  // valid to
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_BLOCK_OUT ───────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_BLOCK_OUT')) {
            Schema::create('ZEPMS_EM_BLOCK_OUT', function (Blueprint $t) {
                $t->bigIncrements('block_id');
                $this->tenantColumns($t);
                $t->string('BUKRS')->nullable();      // company code
                $t->string('ESTNR')->nullable();      // estate code
                $t->string('DIVNR')->nullable();      // division code
                $t->string('BLOCK')->nullable();      // block code
                $t->string('BNAME')->nullable();      // block name
                $t->string('BSTATE')->nullable();     // block state
                $t->string('BHA')->nullable();        // hectarage
                $t->string('POINT')->nullable();      // total palm
                $t->string('PLBLK')->nullable();      // is planted
                $t->string('CROP_TYPE')->nullable();  // crop type
                $t->string('KDATB')->nullable();      // valid from
                $t->string('KDATE')->nullable();      // valid to
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EMPLOYEE_OUT (note: no _EM_ segment, matches CI4) ───────────
        if (! Schema::hasTable('ZEPMS_EMPLOYEE_OUT')) {
            Schema::create('ZEPMS_EMPLOYEE_OUT', function (Blueprint $t) {
                $t->bigIncrements('employee_id');
                $this->tenantColumns($t);
                $t->string('BUKRS')->nullable();  // company code
                $t->string('ESTNR')->nullable();  // estate code
                $t->string('DIVNR')->nullable();  // division code
                $t->string('PRFNR')->nullable();  // profile
                $t->string('EMPNR')->nullable();  // employee code
                $t->string('ENAME')->nullable();  // employee name
                $t->string('KDATB')->nullable();  // valid from
                $t->string('KDATE')->nullable();  // valid to
                $t->string('JBCDE')->nullable();  // job code
                $t->string('JBTYP')->nullable();  // job type
                $t->string('SEX')->nullable();    // sex
                $t->string('STATS')->nullable();  // status
                $t->string('WOPXD')->nullable();  // work permit exp date
                $t->string('DEPNR')->nullable();  // department
                $t->string('LIFNR')->nullable();  // vendor
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ZEPMS_EM_ESTATE_OUT');
        Schema::dropIfExists('ZEPMS_EM_DIVISION_OUT');
        Schema::dropIfExists('ZEPMS_EM_BLOCK_OUT');
        Schema::dropIfExists('ZEPMS_EMPLOYEE_OUT');
    }

    /** Shared multi-tenant columns for every staging table. */
    private function tenantColumns(Blueprint $t): void
    {
        $t->string('company_code')->nullable();          // EPMS tenant key
        $t->string('country_code', 5)->default('MY');    // from Estate Settings
        $t->string('country_no', 5)->default('1');       // country prefix
    }
};
