<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAP master-data staging tables — batch 3.
 * Sloc, Destination, Receiving Point, GL Account, WBS, VRA, Measurement Point, Coconut Material.
 * Same multi-tenant convention: company_code + country_code(MY) + country_no(1).
 * (GLA Order is CRUD/CSV-only — no staging table.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ZEPMS_STORLOC_OUT (Storage Location) ─────────────────────────────
        if (! Schema::hasTable('ZEPMS_STORLOC_OUT')) {
            Schema::create('ZEPMS_STORLOC_OUT', function (Blueprint $t) {
                $t->bigIncrements('sloc_id');
                $this->tenantColumns($t);
                $t->string('LGORT')->nullable();  // sloc code
                $t->string('LGOBE')->nullable();  // sloc desc
                $t->string('WERKS')->nullable();  // plant
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_DESTINATION_OUT ─────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_DESTINATION_OUT')) {
            Schema::create('ZEPMS_EM_DESTINATION_OUT', function (Blueprint $t) {
                $t->bigIncrements('destination_id');
                $this->tenantColumns($t);
                $t->string('WERKS')->nullable();  // destination code
                $t->string('NAME1')->nullable();  // destination name
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_RECEIVING_OUT (Ramp) ────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_RECEIVING_OUT')) {
            Schema::create('ZEPMS_EM_RECEIVING_OUT', function (Blueprint $t) {
                $t->bigIncrements('receiving_point_id');
                $this->tenantColumns($t);
                $t->string('VALUE')->nullable();  // receiving point code
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_GL_OUT (GL Account — Cost Center) ──────────────────────────
        if (! Schema::hasTable('ZEPMS_GL_OUT')) {
            Schema::create('ZEPMS_GL_OUT', function (Blueprint $t) {
                $t->bigIncrements('glacc_id');
                $this->tenantColumns($t);
                $t->string('SAKNR')->nullable();  // account number
                $t->string('TXT50')->nullable();  // account desc
                $t->string('BUKRS')->nullable();  // company code
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_WBS_OUT ────────────────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_WBS_OUT')) {
            Schema::create('ZEPMS_WBS_OUT', function (Blueprint $t) {
                $t->bigIncrements('wbs_id');
                $this->tenantColumns($t);
                $t->string('POSID')->nullable();  // wbs code
                $t->string('POST1')->nullable();  // wbs name
                $t->string('GRPID')->nullable();  // group code
                $t->string('GRPDS')->nullable();  // group name
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_VRA_OUT (License Number) ────────────────────────────────
        if (! Schema::hasTable('ZEPMS_EM_VRA_OUT')) {
            Schema::create('ZEPMS_EM_VRA_OUT', function (Blueprint $t) {
                $t->bigIncrements('vra_id');
                $this->tenantColumns($t);
                $t->string('LICENSE_NUM')->nullable();  // license number
                $t->string('AUFNR')->nullable();        // order number
                $t->string('EQUNR')->nullable();        // equipment code
                $t->string('EQART')->nullable();        // object type
                $t->string('WERKS')->nullable();        // plant
                $t->string('DATAB')->nullable();        // valid from
                $t->string('DATBI')->nullable();        // valid to
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_PM_MEASPOINT_OUT ───────────────────────────────────────────
        if (! Schema::hasTable('ZEPMS_PM_MEASPOINT_OUT')) {
            Schema::create('ZEPMS_PM_MEASPOINT_OUT', function (Blueprint $t) {
                $t->bigIncrements('meas_point_id');
                $this->tenantColumns($t);
                $t->string('BUKRS')->nullable();    // company code
                $t->string('WERKS')->nullable();    // plant
                $t->string('AUFNR')->nullable();    // vra order number
                $t->string('EQUNR')->nullable();    // equipment code
                $t->string('OBJNR')->nullable();    // equipment object number
                $t->string('POINT')->nullable();    // point
                $t->string('UNITTXT')->nullable();  // unit
                $t->string('PTTXT')->nullable();    // description
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_EM_COCONUT_MATERIAL_OUT ────────────────────────────────────
        // CI4 reuses ZEPMS_EM_MATERIAL_OUT with MATKL=TR011 filter, but we keep a
        // dedicated staging table so palm-material staging is not clobbered.
        if (! Schema::hasTable('ZEPMS_EM_COCONUT_MATERIAL_OUT')) {
            Schema::create('ZEPMS_EM_COCONUT_MATERIAL_OUT', function (Blueprint $t) {
                $t->bigIncrements('coconut_material_id');
                $this->tenantColumns($t);
                $t->string('MATNR')->nullable();  // material code
                $t->string('MAKTX')->nullable();  // material desc
                $t->string('MEINS')->nullable();  // uom
                $t->string('WERKS')->nullable();  // plant
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'ZEPMS_STORLOC_OUT', 'ZEPMS_EM_DESTINATION_OUT', 'ZEPMS_EM_RECEIVING_OUT',
            'ZEPMS_GL_OUT', 'ZEPMS_WBS_OUT', 'ZEPMS_EM_VRA_OUT', 'ZEPMS_PM_MEASPOINT_OUT',
            'ZEPMS_EM_COCONUT_MATERIAL_OUT',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }

    private function tenantColumns(Blueprint $t): void
    {
        $t->string('company_code')->nullable();
        $t->string('country_code', 5)->default('MY');
        $t->string('country_no', 5)->default('1');
    }
};
