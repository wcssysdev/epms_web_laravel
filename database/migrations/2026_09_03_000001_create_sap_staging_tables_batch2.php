<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAP master-data staging tables — batch 2.
 * Activity, Vendor, Material, Worktype, Work Center, Cost Center.
 * Same multi-tenant convention as batch 1: company_code + country_code(MY) + country_no(1).
 * (Device is CSV/CRUD-only in CI4 — no staging table.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ZEPMS_ACTIVITY_OUT ───────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_ACTIVITY_OUT')) {
            Schema::create('ZEPMS_ACTIVITY_OUT', function (Blueprint $t) {
                $t->bigIncrements('activity_id');
                $this->tenantColumns($t);
                $t->string('ACTVT_NO')->nullable();      // activity code
                $t->string('ACTVT_NAME')->nullable();    // activity name
                $t->string('AMEIN')->nullable();         // uom name
                $t->string('AMEIN2')->nullable();        // uom
                $t->string('BLOCK')->nullable();         // cost by block (X)
                $t->string('COST_CENTER')->nullable();   // cost by cost center (X)
                $t->string('AUC')->nullable();           // cost by auc (X)
                $t->string('ORDER_NUMBER')->nullable();  // cost by order number (X)
                $t->string('BLOCK_LC')->nullable();      // block is LC (X)
                $t->string('BLOCK_IMMATURE')->nullable();
                $t->string('BLOCK_SCOUT')->nullable();
                $t->string('BLOCK_MATURE')->nullable();
                $t->string('WRK_GRP')->nullable();       // group code
                $t->string('DTWBS')->nullable();         // wbs required (X)
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_VENDOR_OUT ──────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_VENDOR_OUT')) {
            Schema::create('ZEPMS_EM_VENDOR_OUT', function (Blueprint $t) {
                $t->bigIncrements('vendor_id');
                $this->tenantColumns($t);
                $t->string('LIFNR')->nullable();  // vendor code
                $t->string('NAME1')->nullable();  // vendor name
                $t->string('WERKS')->nullable();  // plant code
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_MATERIAL_OUT ────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_MATERIAL_OUT')) {
            Schema::create('ZEPMS_EM_MATERIAL_OUT', function (Blueprint $t) {
                $t->bigIncrements('material_id');
                $this->tenantColumns($t);
                $t->string('MATNR')->nullable();  // material code
                $t->string('MAKTX')->nullable();  // material name
                $t->string('WERKS')->nullable();  // plant code
                $t->string('MEINS')->nullable();  // uom
                $t->string('MTART')->nullable();  // material type
                $t->string('MATKL')->nullable();  // material group
                $t->string('LGORT')->nullable();  // sloc
                $t->string('CHARG')->nullable();  // batch
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_PM_WORKTYPE_OUT ────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_PM_WORKTYPE_OUT')) {
            Schema::create('ZEPMS_PM_WORKTYPE_OUT', function (Blueprint $t) {
                $t->bigIncrements('worktype_id');
                $this->tenantColumns($t);
                $t->string('BUKRS')->nullable();  // company code
                $t->string('AUART')->nullable();  // worktype code
                $t->string('BEZEI')->nullable();  // worktype name
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_WORK_CENTER_OUT ─────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_WORK_CENTER_OUT')) {
            Schema::create('ZEPMS_EM_WORK_CENTER_OUT', function (Blueprint $t) {
                $t->bigIncrements('work_center_id');
                $this->tenantColumns($t);
                $t->string('BUKRS')->nullable();  // company code
                $t->string('WERKS')->nullable();  // plant code
                $t->string('ESTNR')->nullable();  // estate code
                $t->string('SPART')->nullable();  // division code
                $t->string('ARBPL')->nullable();  // work center code
                $t->string('KTEXT')->nullable();  // work center name
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_COST_CENTER_OUT ─────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_COST_CENTER_OUT')) {
            Schema::create('ZEPMS_EM_COST_CENTER_OUT', function (Blueprint $t) {
                $t->bigIncrements('cc_id');
                $this->tenantColumns($t);
                $t->string('KOSTL')->nullable();  // cc code
                $t->string('LTEXT')->nullable();  // cc desc
                $t->string('BUKRS')->nullable();  // company code
                $t->string('GSBER')->nullable();  // business area
                $t->string('DATAB')->nullable();  // valid from
                $t->string('DATBI')->nullable();  // valid to
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ZEPMS_ACTIVITY_OUT');
        Schema::dropIfExists('ZEPMS_EM_VENDOR_OUT');
        Schema::dropIfExists('ZEPMS_EM_MATERIAL_OUT');
        Schema::dropIfExists('ZEPMS_PM_WORKTYPE_OUT');
        Schema::dropIfExists('ZEPMS_EM_WORK_CENTER_OUT');
        Schema::dropIfExists('ZEPMS_EM_COST_CENTER_OUT');
    }

    private function tenantColumns(Blueprint $t): void
    {
        $t->string('company_code')->nullable();
        $t->string('country_code', 5)->default('MY');
        $t->string('country_no', 5)->default('1');
    }
};
