<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAP master-data staging tables — batch 4 (multi-column SAP masters).
 * Sales Order, Purchase Order, Maintenance Order.
 * Same multi-tenant convention: company_code + country_code(MY) + country_no(1).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ZEPMS_SD_SORD_OUT (Sales Order) ──────────────────────────────────
        if (! Schema::hasTable('ZEPMS_SD_SORD_OUT')) {
            Schema::create('ZEPMS_SD_SORD_OUT', function (Blueprint $t) {
                $t->bigIncrements('sales_order_id');
                $this->tenantColumns($t);
                $t->string('WERKS')->nullable();   // plant
                $t->string('VBELN')->nullable();   // sales order no
                $t->string('POSNR')->nullable();   // item no
                $t->string('BSTNK')->nullable();   // customer reference
                $t->string('KUNNR')->nullable();   // customer code
                $t->string('MATNR')->nullable();   // material code
                $t->string('KWMENG')->nullable();  // item qty
                $t->string('VRKME')->nullable();   // item uom
                $t->string('ZTERM')->nullable();   // payment term
                $t->string('ABGRU')->nullable();   // reason for rejection
                $t->string('ARKTX')->nullable();   // item description
                $t->string('TYPE')->nullable();    // sales order type
                $t->string('ERDAT')->nullable();   // sap created date
                $t->string('ERNAM')->nullable();   // sap created by
                $t->string('AUDAT')->nullable();   // sales order date
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_PO_OUT (Purchase Order — header+detail flattened) ──────────
        if (! Schema::hasTable('ZEPMS_PO_OUT')) {
            Schema::create('ZEPMS_PO_OUT', function (Blueprint $t) {
                $t->bigIncrements('po_id');
                $this->tenantColumns($t);
                $t->string('EBELN')->nullable();   // po number
                $t->string('BSART')->nullable();   // po type
                $t->string('BEDAT')->nullable();   // sap created date
                $t->string('WERKS')->nullable();   // plant
                $t->string('EKORG')->nullable();   // organization
                $t->string('EKGRP')->nullable();   // po group
                $t->string('LIFNR')->nullable();   // supplier account
                $t->string('NAME1')->nullable();   // supplier name
                $t->string('EBELP')->nullable();   // material line num
                $t->string('MATNR')->nullable();   // material code
                $t->string('TXZ01')->nullable();   // material name
                $t->string('MATKL')->nullable();   // material group
                $t->string('MENGE')->nullable();   // qty order
                $t->string('MEINS')->nullable();   // uom
                $t->string('WAERS')->nullable();   // currency key
                $t->string('NETPR')->nullable();   // net price
                $t->string('PEINH')->nullable();   // unit price
                $t->string('BPRME')->nullable();   // order price unit
                $t->string('NETWR')->nullable();   // net value
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }

        // ── ZEPMS_MAINT_ORDER_OUT (Maintenance Order) ────────────────────────
        if (! Schema::hasTable('ZEPMS_MAINT_ORDER_OUT')) {
            Schema::create('ZEPMS_MAINT_ORDER_OUT', function (Blueprint $t) {
                $t->bigIncrements('mo_id');
                $this->tenantColumns($t);
                $t->string('AUFNR')->nullable();   // order number
                $t->string('KTEXT')->nullable();   // order desc
                $t->string('AUART')->nullable();   // sales doc type
                $t->string('GSBER')->nullable();   // business area
                $t->string('BUKRS')->nullable();   // company code
                $t->string('WERKS')->nullable();   // plant
                $t->timestamp('fetched_at')->nullable();
                $t->index(['company_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ZEPMS_SD_SORD_OUT');
        Schema::dropIfExists('ZEPMS_PO_OUT');
        Schema::dropIfExists('ZEPMS_MAINT_ORDER_OUT');
    }

    private function tenantColumns(Blueprint $t): void
    {
        $t->string('company_code')->nullable();
        $t->string('country_code', 5)->default('MY');
        $t->string('country_no', 5)->default('1');
    }
};
