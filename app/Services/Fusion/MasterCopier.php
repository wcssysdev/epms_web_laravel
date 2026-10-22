<?php

namespace App\Services\Fusion;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * MasterCopier — the master-data copy stage of estate-db-fusion (spec task 7,
 * requirements R11.1–R11.6). Driven by the `fusion:copy-masters` command
 * ({@see \App\Console\Commands\Fusion\CopyMastersCommand}).
 *
 * Spec authority:
 *   - design.md LLD-3 (table categories + per-category transform rules),
 *     LLD-5 (tenant seeding — what this stage does NOT touch), LLD-8 (PK /
 *     collision handling), LLD-9 (idempotency), sequence diagram (c) "master
 *     copy".
 *   - requirements.md R11.1 (full copy, no date filter), R11.2 (GLOBAL master
 *     natural-key upsert deduped across the 12 DBs), R11.3/R11.4 (tenant master
 *     company_id/country_id inject + drop source PK + audit remap), R11.5 (never
 *     read res_data/audit_trail), R11.6 ((company_id, business_key) -> new id
 *     map), R13.1/R13.2/R13.5 (cross-engine batched insert, type coercion,
 *     record-and-continue), R14.3 (idempotent re-run: business-key upsert for
 *     globals, (company_id, business_key) keys for tenant masters).
 *
 * ## What THIS task (7.1) implements — full master copy with tenant handling
 *
 * {@see copy()} iterates the 12 source DBs via SourceRegistry; for each it
 * classifies the source tables ({@see TableTaxonomy}) and processes ONLY the
 * two master categories (R11.5 — res_data/audit_trail are EXCLUDE and all other
 * categories are handled elsewhere, so they are skipped here):
 *
 *   - GLOBAL_MASTER (R11.2): target table has NO company_id. Rows are UPSERTED
 *     by a NATURAL/BUSINESS KEY, deduped across all 12 DBs so the same global
 *     row seen in several DBs collapses to ONE target row. No company_id is
 *     injected (global masters have none). Naturally idempotent (R14.3).
 *
 *   - TENANT_MASTER (R11.3/R11.4): target table HAS company_id. company_id is
 *     injected (and country_id where the TARGET column exists — the taxonomy's
 *     injectCountryId flag), the source PK is dropped so SQL Server assigns a
 *     fresh identity, and the audit columns are remapped — all via the shared
 *     {@see RowTransformer}. A (company_id, business_key) -> new target id entry
 *     is recorded in the fusion_ledger ('tenant_master_id') so later child/detail
 *     copiers (task 8) can resolve the new identity (R11.6), AND so a re-run
 *     consults the ledger BEFORE inserting and never double-inserts (R14.3).
 *
 * ## What THIS task (7.2) hardens — PROVABLE, OBSERVABLE idempotency (R14.3/R14.5)
 *
 * 7.1 got the mechanism right; 7.2 formalises it so a second full run is a
 * PROVABLE clean no-op the summary SHOWS, without altering a first run's state:
 *
 *   1. GLOBAL robustness + new-vs-existing split: BEFORE each GLOBAL upsert the
 *      copier asks the LIVE TARGET whether the natural/business key already
 *      exists (authoritative, independent of ledger state), classifying the row
 *      as INSERTED (new) or EXISTING. `updateOrInsert` still runs either way so
 *      the ONE row is updated in place — the same key seen in DB #2..#12, or on a
 *      re-run, never duplicates. A 'global_master_key' ledger entry ("table|bk")
 *      is recorded once per key for cross-DB within-run dedup + provenance, so a
 *      re-run reports globalInserted=0, globalExisting=<N> and ROW COUNTS are
 *      stable (updateOrInsert may touch updated_at; it never adds rows).
 *
 *   2. TENANT: one shared ledger-key formatter ({@see tenantLedgerKey()}) builds
 *      the key for BOTH the pre-insert lookup and the write, so a re-run always
 *      hits. The 'tenant_master_id' value (the new identity) is recorded ONLY on
 *      the first insert (the lookup short-circuits before any write on re-run), so
 *      the recorded id is never overwritten.
 *
 *   3. Observable counters: the summary exposes globalInserted/globalExisting
 *      (new GLOBAL rows vs. already-present) alongside tenantInserted/
 *      tenantExisting, and perTable distinguishes written-new vs existing. The
 *      command prints a clear verdict ("Idempotent re-run: 0 new tenant masters,
 *      0 new global masters") when nothing new was written.
 *
 *   4. No ledger growth on re-run: every ledger write (tenant_master_id,
 *      global_master_key, note, error) UPSERTS by the stable (entry_type,
 *      key_text) pair, so a second run does not grow fusion_ledger beyond
 *      genuinely new source rows.
 *
 * None of this changes COLUMN_MAP/BUSINESS_KEY semantics, the m_*-only guard,
 * the RowTransformer usage, or the record-and-continue error handling from 7.1.
 *
 * ## CI3 source schema reality (verified live against the 12 source DBs)
 *
 * CI3 estate DBs name their master columns with a PER-TABLE PREFIX that the
 * multi-tenant target drops or renames, e.g.:
 *   - source m_glacc.m_glacc_account_number  -> target m_glacc.account_number
 *   - source m_activity.activity_code         -> target m_activity.activity_code
 *   - source m_attendance.attendance_id       -> (target identity, dropped)
 * A blind column intersect would therefore silently drop most columns and, for
 * several tables (m_glacc, m_sloc, m_meas_point, ...), copy NOTHING usable. So
 * this copier uses an EXPLICIT per-table source->target column map
 * ({@see COLUMN_MAP}) for the known masters, applied BEFORE the RowTransformer.
 * The explicit map also fixes the business key per table ({@see BUSINESS_KEY}).
 *
 * For a master NOT in the explicit maps the copier falls back to a conservative
 * same-name intersect (source columns that already match a live target column),
 * and derives a business key from a sensible unique non-id column. If NO usable
 * business key can be determined, the table is RECORDED AS A NOTE AND SKIPPED
 * rather than guess-inserting duplicates (R11.2 intent). This never fabricates
 * a key.
 *
 * ## Schema-drift safe writes (R-robustness, mirrors RbacMigrator)
 * Every write intersects the mapped row with the LIVE target column listing
 * ({@see targetColumns()} + array_intersect_key) so only columns the live table
 * actually has are written — the exact pattern RbacMigrator uses.
 *
 * ## Error handling (R13.5)
 * A per-row RowTransformException (type/coercion failure) is recorded to the
 * error ledger and the batch CONTINUES — a single bad row never aborts the DB.
 * A whole-table failure is recorded and the NEXT table is attempted; a whole-DB
 * failure is recorded and the NEXT DB is attempted. The source connection is
 * always closed in a finally.
 *
 * The behaviour lives in this service (unit/behaviour testable); the command is
 * a thin wrapper that wires options/output and calls {@see copy()}.
 */
class MasterCopier
{
    /** Target connection name (SQL Server EPMS via ODBC). */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /** fusion_ledger entry_type: (company_id, business_key) -> new target id (R11.6/R14.3). */
    public const LEDGER_TENANT_MASTER_ID = 'tenant_master_id';

    /**
     * fusion_ledger entry_type: a GLOBAL master natural key already upserted
     * (R14.3/R14.5). key_text = "table|business_key" (globals have no company_id,
     * so the key is deduped across all 12 DBs). Written once (first time a key is
     * seen) and never overwritten; lets a re-run — and DB #2..#12 within one run —
     * detect the same natural key as ALREADY-UPSERTED rather than new. 7.2.
     */
    public const LEDGER_GLOBAL_MASTER_KEY = 'global_master_key';

    /** fusion_ledger entry_type: a skipped row / coercion failure (R13.5). */
    public const LEDGER_ERROR = 'error';

    /** fusion_ledger entry_type: a non-fatal note (ambiguous key / skipped table). */
    public const LEDGER_NOTE = 'note';

    /** Insert chunk size for batched cross-engine inserts (R13.1). */
    public const INSERT_CHUNK = 200;

    /** Source read chunk size. */
    public const READ_CHUNK = 1000;

    /**
     * Explicit per-table BUSINESS KEY (target column name) used for dedup /
     * upsert / the (company_id, business_key) ledger entry.
     *
     * GLOBAL masters: upserted by this target column, deduped across all 12 DBs.
     * TENANT masters: the ledger business key is (company_id, <this column>).
     *
     * These are the natural/business keys verified against the live source and
     * target schemas. A table absent here falls back to a derived key (see
     * {@see deriveBusinessKey()}); if that fails too the table is skipped with a
     * note (never guessed).
     *
     * @var array<string,string>
     */
    private const BUSINESS_KEY = [
        // --- GLOBAL masters (no company_id in target) ---
        'm_attendance'             => 'attendance_code',
        'm_uom'                    => 'uom_code',
        'm_movement_type'          => 'mvt_type_code',
        'm_harvest_method'         => 'mhm_indicator',
        'm_coconut_activity_type'  => 'coconut_activity_type_code',
        'm_deduction_code'         => 'deduction_code',
        'm_order_header'           => 'm_order_header_number',
        'm_po_header'              => 'm_po_header_number',

        // --- TENANT masters (company_id in target) ---
        'm_activity'                   => 'activity_code',
        'm_assistant_manager_division' => 'assistant_manager_code',
        'm_bin'                        => 'bin_code',
        'm_block'                      => 'block_code',
        'm_confirmation_text'          => 'ctext_code',
        'm_cost_center'                => 'cc_code',
        'm_cost_control_mapping'       => 'activity_code_start',
        'm_customer_code'              => 'customer_code',
        'm_deduction_type'             => 'deduction_type_code',
        'm_destination'                => 'destination_code',
        'm_devices'                    => 'device_code',
        'm_division'                   => 'division_code',
        'm_employee'                   => 'employee_code',
        'm_field_staff_gang'           => 'field_staff_gang_code',
        'm_field_staff_kemandoran'     => 'field_staff_employee_code',
        'm_gang_employee'              => 'gang_employee_code',
        'm_gigr_wbs'                   => 'wbs_code',
        'm_glacc'                      => 'account_number',
        'm_glacc_gi_order'             => 'account_number',
        'm_maintenance_order'          => 'order_number',
        'm_material'                   => 'material_code',
        'm_meas_point'                 => 'equipment_code',
        'm_non_palm_material'          => 'material_code',
        'm_receiving_point'            => 'receiving_point_code',
        'm_report_oph'                 => 'block_code',
        'm_sales_order'                => 'sales_order_no',
        'm_sloc'                       => 'sloc_code',
        'm_tph'                        => 'tph_code',
        'm_vendor'                     => 'vendor_code',
        'm_vra'                        => 'vra_order_number',
        'm_wbs'                        => 'wbs_code',
        'm_work_center'                => 'work_center_code',
        'm_worktype'                   => 'worktype_code',
    ];

    /**
     * Explicit per-table SOURCE->TARGET column map for masters whose source
     * (CI3, prefixed) column names do NOT match the target column names. Only
     * the tables that NEED remapping are listed; a table absent here is copied
     * by same-name intersect (its source columns already match the target).
     *
     * Keys are SOURCE columns, values are TARGET columns. Source audit columns
     * (created_date/created_time/updated_*) are renamed to the UNPREFIXED names
     * RowTransformer understands so the generic audit remap (R11.4) produces
     * created_at/updated_at. The source id column is intentionally NOT mapped
     * (dropped — tenant masters re-identity; globals upsert by business key).
     *
     * @var array<string,array<string,string>>
     */
    private const COLUMN_MAP = [
        // ---- GLOBAL masters with prefixed source columns ----
        'm_attendance' => [
            'attendance_code'         => 'attendance_code',
            'attendance_desc'         => 'attendance_desc',
            'attendance_created_by'   => 'created_by',
            'attendance_created_date' => 'created_date',
            'attendance_created_time' => 'created_time',
            'attendance_updated_by'   => 'updated_by',
            'attendance_updated_date' => 'updated_date',
            'attendance_updated_time' => 'updated_time',
        ],
        'm_uom' => [
            'uom_code'         => 'uom_code',
            'uom_desc'         => 'uom_desc',
            'uom_created_by'   => 'created_by',
            'uom_created_date' => 'created_date',
            'uom_created_time' => 'created_time',
            'uom_updated_by'   => 'updated_by',
            'uom_updated_date' => 'updated_date',
            'uom_updated_time' => 'updated_time',
        ],
        'm_movement_type' => [
            'mvt_type_code'         => 'mvt_type_code',
            'mvt_type_doc_type'     => 'mvt_type_doc_type',
            'mvt_type_desc'         => 'mvt_type_desc',
            'mvt_type_created_by'   => 'created_by',
            'mvt_type_created_date' => 'created_date',
            'mvt_type_created_time' => 'created_time',
            'mvt_type_updated_by'   => 'updated_by',
            'mvt_type_updated_date' => 'updated_date',
            'mvt_type_updated_time' => 'updated_time',
        ],
        'm_harvest_method' => [
            'mhm_indicator'         => 'mhm_indicator',
            'mhm_abbreviation'      => 'mhm_abbreviation',
            'mhm_description'       => 'mhm_description',
            'mhm_order_number_flag' => 'mhm_order_number_flag',
            'mhm_created_by'        => 'created_by',
            'mhm_created_date'      => 'created_date',
            'mhm_created_time'      => 'created_time',
            'mhm_updated_by'        => 'updated_by',
            'mhm_updated_date'      => 'updated_date',
            'mhm_updated_time'      => 'updated_time',
        ],
        'm_coconut_activity_type' => [
            'coconut_activity_type_code'         => 'coconut_activity_type_code',
            'coconut_activity_type_desc'         => 'coconut_activity_type_desc',
            'coconut_activity_type_created_by'   => 'created_by',
            'coconut_activity_type_created_date' => 'created_date',
            'coconut_activity_type_created_time' => 'created_time',
            'coconut_activity_type_updated_by'   => 'updated_by',
            'coconut_activity_type_updated_date' => 'updated_date',
            'coconut_activity_type_updated_time' => 'updated_time',
        ],
        'm_deduction_code' => [
            'deduction_code'             => 'deduction_code',
            'deduction_code_description' => 'deduction_code_description',
            'deduction_code_uom'         => 'deduction_code_uom',
            'deduction_code_rate'        => 'deduction_code_rate',
            // target keeps CI3-style prefixed audit columns for this table
            'deduction_created_by'       => 'deduction_created_by',
            'deduction_created_date'     => 'deduction_created_date',
            'deduction_created_time'     => 'deduction_created_time',
            'deduction_updated_by'       => 'deduction_updated_by',
            'deduction_updated_date'     => 'deduction_updated_date',
            'deduction_updated_time'     => 'deduction_updated_time',
        ],
        // m_order_header / m_po_header: target column names equal source names
        // (verified) EXCEPT audit — handled by same-name intersect, so NOT
        // listed here (no remap needed).

        // ---- TENANT masters with prefixed source columns ----
        'm_activity' => [
            'activity_code'                 => 'activity_code',
            'activity_name'                 => 'activity_name',
            'activity_uom'                  => 'activity_uom',
            'activity_uom_name'             => 'activity_uom_name',
            'activity_group_code'           => 'activity_group_code',
            'activity_cost_by_block'        => 'cost_by_block',
            'activity_cost_by_auc'          => 'cost_by_auc',
            'activity_cost_by_order_number' => 'cost_by_order_number',
            'activity_cost_by_cost_center'  => 'cost_by_cost_center',
            'activity_block_is_lc'          => 'block_is_lc',
            'activity_block_is_immature'    => 'block_is_immature',
            'activity_block_is_mature'      => 'block_is_mature',
            'activity_block_is_scout'       => 'block_is_scout',
            'activity_is_wbs_required'      => 'is_wbs_required',
            'activity_created_by'           => 'created_by',
            'activity_created_date'         => 'created_date',
            'activity_created_time'         => 'created_time',
            'activity_updated_by'           => 'updated_by',
            'activity_updated_date'         => 'updated_date',
            'activity_updated_time'         => 'updated_time',
        ],
        'm_assistant_manager_division' => [
            'assistant_manager_code'          => 'assistant_manager_code',
            'assistant_manager_name'          => 'assistant_manager_name',
            'assistant_manager_division_code' => 'division_code',
            'assistant_manager_division_name' => 'division_name',
            'assistant_manager_created_by'    => 'created_by',
            'assistant_manager_created_date'  => 'created_date',
            'assistant_manager_created_time'  => 'created_time',
            'assistant_manager_updated_by'    => 'updated_by',
            'assistant_manager_updated_date'  => 'updated_date',
            'assistant_manager_updated_time'  => 'updated_time',
        ],
        'm_bin' => [
            'bin_code'         => 'bin_code',
            'bin_created_by'   => 'created_by',
            'bin_created_date' => 'created_date',
            'bin_created_time' => 'created_time',
            'bin_updated_by'   => 'updated_by',
            'bin_updated_date' => 'updated_date',
            'bin_updated_time' => 'updated_time',
        ],
        'm_block' => [
            'block_estate_code'   => 'estate_code',
            'block_division_code' => 'division_code',
            'block_code'          => 'block_code',
            'block_name'          => 'block_name',
            'block_hectarage'     => 'block_hectarage',
            'block_planted_date'  => 'block_planted_date',
            'block_valid_from'    => 'valid_from',
            'block_valid_to'      => 'valid_to',
            'block_state'         => 'block_state',
            'block_is_planted'    => 'is_planted',
            'block_crop_type'     => 'crop_type',
            'block_total_palm'    => 'total_palm',
            'block_created_by'    => 'created_by',
            'block_created_date'  => 'created_date',
            'block_created_time'  => 'created_time',
            'block_updated_by'    => 'updated_by',
            'block_updated_date'  => 'updated_date',
            'block_updated_time'  => 'updated_time',
        ],
        'm_confirmation_text' => [
            'ctext_code'         => 'ctext_code',
            'ctext_text'         => 'ctext_text',
            'ctext_desc'         => 'ctext_desc',
            'ctext_created_by'   => 'created_by',
            'ctext_created_date' => 'created_date',
            'ctext_created_time' => 'created_time',
            'ctext_updated_by'   => 'updated_by',
            'ctext_updated_date' => 'updated_date',
            'ctext_updated_time' => 'updated_time',
        ],
        'm_cost_center' => [
            'cc_code'              => 'cc_code',
            'cc_desc'              => 'cc_desc',
            'cc_gsber'             => 'cc_gsber',
            'cc_valid_from'        => 'valid_from',
            'cc_valid_to'          => 'valid_to',
            'cc_created_by'        => 'created_by',
            'cc_created_timestamp' => 'created_date',
            'cc_updated_by'        => 'updated_by',
            'cc_updated_timestamp' => 'updated_date',
        ],
        'm_cost_control_mapping' => [
            'activity_code_start'                  => 'activity_code_start',
            'activity_code_end'                    => 'activity_code_end',
            'cost_by_block'                        => 'cost_by_block',
            'cost_by_auc'                          => 'cost_by_auc',
            'cost_by_order_number'                 => 'cost_by_order_number',
            'cost_by_cost_center'                  => 'cost_by_cost_center',
            'cost_control_mapping_created_by'      => 'created_by',
            'cost_control_mapping_created_date'    => 'created_date',
            'cost_control_mapping_created_time'    => 'created_time',
            'cost_control_mapping_updated_by'      => 'updated_by',
            'cost_control_mapping_updated_date'    => 'updated_date',
            'cost_control_mapping_updated_time'    => 'updated_time',
        ],
        'm_customer_code' => [
            'customer_code_plant_code'  => 'plant_code',
            'customer_code'             => 'customer_code',
            'customer_code_created_by'  => 'created_by',
            'customer_code_created_date'=> 'created_date',
            'customer_code_created_time'=> 'created_time',
            'customer_code_updated_by'  => 'updated_by',
            'customer_code_updated_date'=> 'updated_date',
            'customer_code_updated_time'=> 'updated_time',
        ],
        'm_deduction_type' => [
            'deduction_type_code'         => 'deduction_type_code',
            'deduction_type_description'  => 'deduction_type_desc',
            'deduction_type_created_by'   => 'created_by',
            'deduction_type_created_date' => 'created_date',
            'deduction_type_created_time' => 'created_time',
            'deduction_type_updated_by'   => 'updated_by',
            'deduction_type_updated_date' => 'updated_date',
            'deduction_type_updated_time' => 'updated_time',
        ],
        'm_destination' => [
            'destination_code'         => 'destination_code',
            'destination_name'         => 'destination_name',
            'destination_created_by'   => 'created_by',
            'destination_created_date' => 'created_date',
            'destination_created_time' => 'created_time',
            'destination_updated_by'   => 'updated_by',
            'destination_updated_date' => 'updated_date',
            'destination_updated_time' => 'updated_time',
        ],
        'm_devices' => [
            'device_code'         => 'device_code',
            'device_estate_code'  => 'estate_code',
            'device_imei'         => 'device_imei',
            'device_created_by'   => 'created_by',
            'device_created_date' => 'created_date',
            'device_created_time' => 'created_time',
            'device_updated_by'   => 'updated_by',
            'device_updated_date' => 'updated_date',
            'device_updated_time' => 'updated_time',
        ],
        'm_division' => [
            'division_estate_code'  => 'estate_code',
            'division_code'         => 'division_code',
            'division_name'         => 'division_name',
            'division_valid_from'   => 'valid_from',
            'division_valid_to'     => 'valid_to',
            'division_created_by'   => 'created_by',
            'division_created_date' => 'created_date',
            'division_created_time' => 'created_time',
            'division_updated_by'   => 'updated_by',
            'division_updated_date' => 'updated_date',
            'division_updated_time' => 'updated_time',
        ],
        'm_employee' => [
            'employee_estate_code'         => 'employee_estate_code',
            'employee_division_code'       => 'employee_division_code',
            'employee_code'                => 'employee_code',
            'employee_name'                => 'employee_name',
            'employee_sex'                 => 'employee_sex',
            'employee_job_code'            => 'employee_job_code',
            'employee_job_type'            => 'employee_job_type',
            'employee_status'              => 'employee_status',
            'employee_stats'               => 'employee_stats',
            'employee_profile'             => 'employee_profile',
            'employee_department'          => 'employee_department',
            'employee_vendor'              => 'employee_vendor',
            'employee_is_internal_estate'  => 'is_internal_estate',
            'employee_valid_from'          => 'valid_from',
            'employee_valid_to'            => 'valid_to',
            'employee_work_permit_exp_date'=> 'work_permit_exp_date',
            'employee_created_by'          => 'created_by',
            'employee_created_date'        => 'created_date',
            'employee_created_time'        => 'created_time',
            'employee_updated_by'          => 'updated_by',
            'employee_updated_date'        => 'updated_date',
            'employee_updated_time'        => 'updated_time',
        ],
        'm_field_staff_gang' => [
            'field_staff_gang_code'          => 'field_staff_gang_code',
            'field_staff_employee_code'      => 'field_staff_employee_code',
            'field_staff_employee_name'      => 'field_staff_employee_name',
            'field_staff_gang_created_by'    => 'created_by',
            'field_staff_gang_created_date'  => 'created_date',
            'field_staff_gang_created_time'  => 'created_time',
            'field_staff_gang_updated_by'    => 'updated_by',
            'field_staff_gang_updated_date'  => 'updated_date',
            'field_staff_gang_updated_time'  => 'updated_time',
        ],
        'm_field_staff_kemandoran' => [
            'field_staff_employee_code'            => 'field_staff_employee_code',
            'field_staff_employee_name'            => 'field_staff_employee_name',
            'mandor_employee_code'                 => 'mandor_employee_code',
            'mandor_employee_name'                 => 'mandor_employee_name',
            'field_staff_kemandoran_created_by'    => 'created_by',
            'field_staff_kemandoran_created_date'  => 'created_date',
            'field_staff_kemandoran_created_time'  => 'created_time',
            'field_staff_kemandoran_updated_by'    => 'updated_by',
            'field_staff_kemandoran_updated_date'  => 'updated_date',
            'field_staff_kemandoran_updated_time'  => 'updated_time',
        ],
        'm_gang_employee' => [
            'gang_code'                    => 'gang_code',
            'gang_employee_code'           => 'gang_employee_code',
            'gang_employee_name'           => 'gang_employee_name',
            'gang_employee_created_by'     => 'created_by',
            'gang_employee_created_date'   => 'created_date',
            'gang_employee_created_time'   => 'created_time',
            'gang_employee_updated_by'     => 'updated_by',
            'gang_employee_updated_date'   => 'updated_date',
            'gang_employee_updated_time'   => 'updated_time',
        ],
        'm_gigr_wbs' => [
            'wbs_code'             => 'wbs_code',
            'wbs_code2'            => 'wbs_code2',
            'wbs_gl_acc_code'      => 'wbs_gl_acc_code',
            'wbs_gl_acc_desc'      => 'wbs_gl_acc_desc',
            'wbs_plant_code'       => 'plant_code',
            'wbs_name'             => 'wbs_name',
            'wbs_created_by'       => 'created_by',
            'wbs_created_timestamp'=> 'created_date',
            'wbs_updated_by'       => 'updated_by',
            'wbs_updated_timestamp'=> 'updated_date',
        ],
        'm_glacc' => [
            'm_glacc_account_number' => 'account_number',
            'm_glacc_desc'           => 'account_desc',
            'm_glacc_created_by'     => 'created_by',
            'm_glacc_created_timestamp' => 'created_date',
            'm_glacc_updated_by'     => 'updated_by',
            'm_glacc_updated_timestamp' => 'updated_date',
        ],
        'm_glacc_gi_order' => [
            'm_glacc_account_number' => 'account_number',
            'm_glacc_desc'           => 'account_desc',
            'm_glacc_created_by'     => 'created_by',
            'm_glacc_created_timestamp' => 'created_date',
            'm_glacc_updated_by'     => 'updated_by',
            'm_glacc_updated_timestamp' => 'updated_date',
        ],
        'm_maintenance_order' => [
            'mo_order_number'    => 'order_number',
            'mo_sales_doc_type'  => 'sales_doc_type',
            'mo_order_desc'      => 'order_desc',
            'mo_plant_code'      => 'plant_code',
            'mo_business_area'   => 'business_area',
            'mo_created_by'      => 'created_by',
            'mo_created_timestamp' => 'created_date',
            'mo_updated_by'      => 'updated_by',
            'mo_updated_timestamp' => 'updated_date',
        ],
        'm_material' => [
            'material_code'         => 'material_code',
            'material_name'         => 'material_name',
            'material_uom'          => 'material_uom',
            'material_plant_code'   => 'plant_code',
            'material_sloc'         => 'sloc_code',
            'material_batch'        => 'material_batch',
            'material_group'        => 'material_group',
            'material_type'         => 'material_type',
            'material_created_by'   => 'created_by',
            'material_created_date' => 'created_date',
            'material_created_time' => 'created_time',
            'material_updated_by'   => 'updated_by',
            'material_updated_date' => 'updated_date',
            'material_updated_time' => 'updated_time',
        ],
        'm_meas_point' => [
            'meas_point_plant_code'            => 'plant_code',
            'meas_point_vra_order_number'      => 'vra_order_number',
            'meas_point_equipment_code'        => 'equipment_code',
            'meas_point_equipment_object_number' => 'equipment_object_number',
            'meas_point_point'                 => 'point',
            'meas_point_unit'                  => 'unit',
            'meas_point_description'           => 'description',
            'meas_point_created_by'            => 'created_by',
            'meas_point_created_date'          => 'created_date',
            'meas_point_created_time'          => 'created_time',
            'meas_point_updated_by'            => 'updated_by',
            'meas_point_updated_date'          => 'updated_date',
            'meas_point_updated_time'          => 'updated_time',
        ],
        'm_non_palm_material' => [
            'non_palm_material_code'         => 'material_code',
            'non_palm_material_desc'         => 'material_desc',
            'non_palm_material_uom'          => 'material_uom',
            'non_palm_material_plant_code'   => 'plant_code',
            'non_palm_material_created_by'   => 'created_by',
            'non_palm_material_created_date' => 'created_date',
            'non_palm_material_created_time' => 'created_time',
            'non_palm_material_updated_by'   => 'updated_by',
            'non_palm_material_updated_date' => 'updated_date',
            'non_palm_material_updated_time' => 'updated_time',
        ],
        'm_receiving_point' => [
            'receiving_point_code'         => 'receiving_point_code',
            'receiving_point_created_by'   => 'created_by',
            'receiving_point_created_date' => 'created_date',
            'receiving_point_created_time' => 'created_time',
            'receiving_point_updated_by'   => 'updated_by',
            'receiving_point_updated_date' => 'updated_date',
            'receiving_point_updated_time' => 'updated_time',
        ],
        'm_report_oph' => [
            'r_oph_estate_code'       => 'estate_code',
            'r_oph_period'            => 'period',
            'r_oph_division_code'     => 'division_code',
            'r_oph_block_code'        => 'block_code',
            'r_oph_brondolan_rate_1'  => 'brondolan_rate_1',
            'r_oph_brondolan_rate_2'  => 'brondolan_rate_2',
            'r_oph_basis'             => 'basis',
            'r_oph_gandeng'           => 'gandeng',
            'r_oph_premi_basis'       => 'premi_basis',
            'r_oph_premi_non_basis'   => 'premi_non_basis',
            'r_oph_hk_rate'           => 'hk_rate',
            'r_oph_created_by'        => 'created_by',
            'r_oph_created_date'      => 'created_date',
            'r_oph_created_time'      => 'created_time',
            'r_oph_updated_by'        => 'updated_by',
            'r_oph_updated_date'      => 'updated_date',
            'r_oph_updated_time'      => 'updated_time',
        ],
        'm_sales_order' => [
            'sales_order_no'                  => 'sales_order_no',
            'sales_order_plant'               => 'plant_code',
            'sales_order_item_no'             => 'item_no',
            'sales_order_customer_reference'  => 'customer_reference',
            'sales_order_customer_code'       => 'customer_code',
            'sales_order_customer_description_1' => 'customer_desc_1',
            'sales_order_customer_description_2' => 'customer_desc_2',
            'sales_order_material_code'       => 'material_code',
            'sales_order_material_desc'       => 'material_desc',
            'sales_order_item_qty'            => 'item_qty',
            'sales_order_item_uom'            => 'item_uom',
            'sales_order_payment_term'        => 'payment_term',
            'sales_order_inco_term_1'         => 'inco_term_1',
            'sales_order_inco_term_2'         => 'inco_term_2',
            'sales_order_reason_for_rejection'=> 'reason_for_rejection',
            'sales_order_type'                => 'sales_order_type',
            'sales_order_date'                => 'sales_order_date',
            'sales_order_item_description'    => 'item_description',
            'sales_order_sap_created_by'      => 'sap_created_by',
            'sales_order_sap_created_date'    => 'sap_created_date',
            'sales_order_created_by'          => 'created_by',
            'sales_order_created_date'        => 'created_date',
            'sales_order_created_time'        => 'created_time',
            'sales_order_updated_by'          => 'updated_by',
            'sales_order_updated_date'        => 'updated_date',
            'sales_order_updated_time'        => 'updated_time',
        ],
        'm_sloc' => [
            'm_sloc_code'             => 'sloc_code',
            'm_sloc_plant'            => 'plant_code',
            'm_sloc_desc'             => 'sloc_desc',
            'm_sloc_created_by'       => 'created_by',
            'm_sloc_created_timestamp'=> 'created_date',
            'm_sloc_updated_by'       => 'updated_by',
            'm_sloc_updated_timestamp'=> 'updated_date',
        ],
        'm_tph' => [
            'tph_estate_code'   => 'estate_code',
            'tph_division_code' => 'division_code',
            'tph_block_code'    => 'block_code',
            'tph_section_code'  => 'section_code',
            'tph_code'          => 'tph_code',
            'tph_valid_from'    => 'valid_from',
            'tph_valid_to'      => 'valid_to',
            'tph_latitude'      => 'latitude',
            'tph_longitude'     => 'longitude',
            'tph_palm_total'    => 'tph_palm_total',
            'tph_created_by'    => 'created_by',
            'tph_created_date'  => 'created_date',
            'tph_created_time'  => 'created_time',
            'tph_updated_by'    => 'updated_by',
            'tph_updated_date'  => 'updated_date',
            'tph_updated_time'  => 'updated_time',
        ],
        'm_vendor' => [
            'vendor_code'         => 'vendor_code',
            'vendor_name'         => 'vendor_name',
            'vendor_plant_code'   => 'plant_code',
            'vendor_created_by'   => 'created_by',
            'vendor_created_date' => 'created_date',
            'vendor_created_time' => 'created_time',
            'vendor_updated_by'   => 'updated_by',
            'vendor_updated_date' => 'updated_date',
            'vendor_updated_time' => 'updated_time',
        ],
        'm_vra' => [
            'vra_license_number' => 'license_number',
            'vra_equipment_code' => 'equipment_code',
            'vra_object_type'    => 'object_type',
            'vra_plant_code'     => 'plant_code',
            'vra_order_number'   => 'vra_order_number',
            'vra_valid_from'     => 'valid_from',
            'vra_valid_to'       => 'valid_to',
            'vra_created_by'     => 'created_by',
            'vra_created_date'   => 'created_date',
            'vra_created_time'   => 'created_time',
            'vra_updated_by'     => 'updated_by',
            'vra_updated_date'   => 'updated_date',
            'vra_updated_time'   => 'updated_time',
        ],
        'm_wbs' => [
            'wbs_code'         => 'wbs_code',
            'wbs_name'         => 'wbs_name',
            'wbs_group_code'   => 'wbs_group_code',
            'wbs_group_name'   => 'wbs_group_name',
            'wbs_created_by'   => 'created_by',
            'wbs_created_date' => 'created_date',
            'wbs_created_time' => 'created_time',
            'wbs_updated_by'   => 'updated_by',
            'wbs_updated_date' => 'updated_date',
            'wbs_updated_time' => 'updated_time',
        ],
        'm_work_center' => [
            'work_center_plant_code'   => 'plant_code',
            'work_center_estate_code'  => 'estate_code',
            'work_center_division_code'=> 'division_code',
            'work_center_code'         => 'work_center_code',
            'work_center_name'         => 'work_center_name',
            'work_center_created_by'   => 'created_by',
            'work_center_created_date' => 'created_date',
            'work_center_created_time' => 'created_time',
            'work_center_updated_by'   => 'updated_by',
            'work_center_updated_date' => 'updated_date',
            'work_center_updated_time' => 'updated_time',
        ],
        'm_worktype' => [
            'worktype_code'         => 'worktype_code',
            'worktype_name'         => 'worktype_name',
            'worktype_created_by'   => 'created_by',
            'worktype_created_date' => 'created_date',
            'worktype_created_time' => 'created_time',
            'worktype_updated_by'   => 'updated_by',
            'worktype_updated_date' => 'updated_date',
            'worktype_updated_time' => 'updated_time',
        ],
    ];

    private readonly ConnectionInterface $target;

    public function __construct(
        private readonly SourceRegistry $registry,
        private readonly TenantResolver $resolver,
        private readonly RowTransformer $transformer,
        ?ConnectionInterface $target = null,
        private readonly ?TableTaxonomy $taxonomy = null,
    ) {
        $this->target = $target ?? DB::connection(config('fusion.target_connection', self::TARGET_CONNECTION));
    }

    /**
     * Copy all master data across the 12 source DBs and return a summary.
     *
     * @param  callable(string $level, string $message):void|null  $progress
     * @param  array{only_db?: ?string, limit?: ?int}  $options
     *     - only_db: restrict to a single source db_name (guarded run).
     *     - limit:   cap source rows read PER TABLE (guarded few-rows smoke run).
     * @return array{
     *     databases:int, tablesProcessed:int, tablesSkipped:int,
     *     globalsUpserted:int, globalInserted:int, globalExisting:int,
     *     tenantInserted:int, tenantExisting:int,
     *     skipped:int, errors:int, notes:int,
     *     perTable:array<string,array{category:string,read:int,written:int,existing:int,errors:int}>
     * }
     *
     * Counter meanings (7.2):
     *   - globalsUpserted: TOTAL GLOBAL rows upserted this run (= globalInserted
     *     + globalExisting). Stable across runs for a fixed source set.
     *   - globalInserted:  GLOBAL natural keys NOT previously present in the
     *     target (genuinely new). 0 on a clean re-run.
     *   - globalExisting:  GLOBAL natural keys already present in the target
     *     (re-upserted in place, no new row).
     *   - tenantInserted:  TENANT rows newly inserted (ledger miss). 0 on re-run.
     *   - tenantExisting:  TENANT rows skipped via the ledger (re-run no-op).
     */
    public function copy(?callable $progress = null, array $options = []): array
    {
        $say = $progress ?? static fn (string $level, string $message) => null;

        $onlyDb = $options['only_db'] ?? null;
        $limit = $options['limit'] ?? null;

        $taxonomy = $this->taxonomy ?? new TableTaxonomy($this->target);

        $summary = [
            'databases'       => 0,
            'tablesProcessed' => 0,
            'tablesSkipped'   => 0,
            'globalsUpserted' => 0,
            'globalInserted'  => 0,
            'globalExisting'  => 0,
            'tenantInserted'  => 0,
            'tenantExisting'  => 0,
            'skipped'         => 0,
            'errors'          => 0,
            'notes'           => 0,
            'perTable'        => [],
        ];

        foreach ($this->registry->all() as $descriptor) {
            if ($onlyDb !== null && $descriptor->dbName !== $onlyDb) {
                continue;
            }

            $tenant = $this->resolver->forEstate($descriptor->estateCode);
            if ($tenant === null) {
                $this->recordError(
                    companyId: null,
                    key: 'tenant_unresolved|'.$descriptor->estateCode,
                    message: sprintf(
                        'db=%s estate=%s: no TenantRef (estate not seeded) — whole DB skipped. Run fusion:seed-tenants first.',
                        $descriptor->dbName, $descriptor->estateCode,
                    ),
                );
                $summary['errors']++;
                $say('error', sprintf('  %s (%s): tenant unresolved — DB skipped (seed tenants first).',
                    $descriptor->dbName, $descriptor->estateCode));
                continue;
            }

            $say('info', sprintf('Copying masters from %s (estate %s, company %s)...',
                $descriptor->dbName, $descriptor->estateCode, $descriptor->companyCode));
            $summary['databases']++;

            try {
                $this->copyOneDatabase($descriptor, $tenant, $taxonomy, $limit, $say, $summary);
            } catch (Throwable $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: 'db_failure|'.$descriptor->dbName,
                    message: sprintf('db=%s: master copy failed: %s', $descriptor->dbName, $e->getMessage()),
                );
                $summary['errors']++;
                $say('error', sprintf('  %s: FAILED — %s', $descriptor->dbName, $e->getMessage()));
            } finally {
                SourceConnection::close();
            }
        }

        return $summary;
    }

    /**
     * Copy the master tables of a single source DB.
     *
     * @param  int|null  $limit
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary mutated in place.
     */
    private function copyOneDatabase(
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TableTaxonomy $taxonomy,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        $pg = SourceConnection::for($descriptor->dbName);

        $plans = $taxonomy->classifyAll($pg);

        foreach ($plans as $table => $plan) {
            // Process ONLY master categories. EXCLUDE/RBAC/TENANT_META/headers/
            // children are NOT this task's job (R11.5 — res_data/audit_trail are
            // already EXCLUDE; everything else is handled by other stages).
            $isGlobal = $plan->category === TableCategory::GLOBAL_MASTER;
            $isTenant = $plan->category === TableCategory::TENANT_MASTER;
            if (! $isGlobal && ! $isTenant) {
                continue;
            }

            // MASTER DATA ONLY: the design's master categories are the `m_*`
            // tables (design LLD-3). The taxonomy's company_id/id-type fallback
            // can mis-bucket non-master source tables (SAP interface `zepms_*`,
            // `login_log`, `log_*`, card/stock/log tables) into a master
            // category because they happen to carry/lack a company_id. Those are
            // NOT master data and are owned by other stages (or are not copied
            // at all), so restrict this stage to real `m_`-prefixed masters.
            // Recorded as a note so the skip is auditable, not silent.
            if (! str_starts_with($table, 'm_')) {
                $this->recordNote(
                    companyId: $tenant->companyId,
                    key: sprintf('non_master_skip|%s', $table),
                    message: sprintf(
                        'db=%s table=%s classified %s by the taxonomy fallback but is not an m_* master — '
                        .'skipped by MasterCopier (not master data; owned by another stage or out of scope).',
                        $descriptor->dbName, $table, $plan->category->value,
                    ),
                );
                $summary['tablesSkipped']++;
                $summary['notes']++;
                continue;
            }

            // The target table must exist on the live target to copy into it.
            if (! $this->targetTableExists($table)) {
                $this->recordNote(
                    companyId: $tenant->companyId,
                    key: sprintf('no_target_table|%s', $table),
                    message: sprintf('db=%s table=%s: no matching target table — skipped.', $descriptor->dbName, $table),
                );
                $summary['tablesSkipped']++;
                $summary['notes']++;
                continue;
            }

            try {
                if ($isGlobal) {
                    $this->copyGlobalMaster($pg, $descriptor, $tenant, $plan, $limit, $say, $summary);
                } else {
                    $this->copyTenantMaster($pg, $descriptor, $tenant, $plan, $limit, $say, $summary);
                }
                $summary['tablesProcessed']++;
            } catch (Throwable $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('table_failure|%s|%s', $descriptor->dbName, $table),
                    message: sprintf('db=%s table=%s: copy failed: %s', $descriptor->dbName, $table, $e->getMessage()),
                );
                $summary['errors']++;
                $say('error', sprintf('    %s: table FAILED — %s', $table, $e->getMessage()));
            }
        }
    }

    // -------------------------------------------------------------------------
    // GLOBAL master copy (R11.2) — natural-key upsert, deduped across 12 DBs.
    // -------------------------------------------------------------------------

    /**
     * Copy a GLOBAL master: upsert by the natural/business key, deduped across
     * all 12 DBs (same key seen in multiple DBs collapses to one target row).
     * No company_id is injected (the taxonomy already set injectCompanyId=false).
     *
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary
     */
    private function copyGlobalMaster(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TablePlan $plan,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        $table = $plan->table;
        $targetCols = $this->targetColumns($table);

        $businessKey = $this->businessKeyFor($table, $targetCols);
        if ($businessKey === null) {
            $this->recordNote(
                companyId: null,
                key: sprintf('ambiguous_key|%s', $table),
                message: sprintf(
                    'db=%s GLOBAL table=%s: no determinable business key (not in BUSINESS_KEY map and no safe '
                    .'unique non-id column) — table SKIPPED (not guess-inserted) per R11.2.',
                    $descriptor->dbName, $table,
                ),
            );
            $summary['tablesSkipped']++;
            $summary['notes']++;
            $say('warn', sprintf('    %s: SKIP (ambiguous business key).', $table));
            return;
        }

        $read = 0;
        $written = 0;
        $errors = 0;

        $inserted = 0;
        $existing = 0;

        $this->eachSourceRow($pg, $table, $limit, function (array $src) use (
            $table, $plan, $tenant, $targetCols, $businessKey, $descriptor,
            &$read, &$written, &$inserted, &$existing, &$errors, &$summary
        ): void {
            $read++;
            try {
                $mapped = $this->mapColumns($table, $src);
                $row = $this->transformer->transform($mapped, $plan, $tenant);
            } catch (RowTransformException $e) {
                $this->recordError(
                    companyId: null,
                    key: sprintf('coerce|%s|%s|%d', $descriptor->dbName, $table, $read),
                    message: sprintf('db=%s table=%s col=%s: %s', $descriptor->dbName, $table, $e->column, $e->getMessage()),
                );
                $errors++;
                $summary['errors']++;
                return;
            }

            $values = $this->writeableValues($table, $row, $targetCols, $businessKey);
            if (! array_key_exists($businessKey, $values) || $this->isBlank($values[$businessKey])) {
                // A row with no business-key value cannot be deduped — skip it.
                $this->recordError(
                    companyId: null,
                    key: sprintf('no_bk|%s|%s|%d', $descriptor->dbName, $table, $read),
                    message: sprintf('db=%s table=%s: GLOBAL row has empty business key "%s" — skipped.',
                        $descriptor->dbName, $table, $businessKey),
                );
                $errors++;
                $summary['errors']++;
                return;
            }

            // Natural-key upsert: dedup across all 12 DBs (R11.2 / R14.3).
            $keyValue = $values[$businessKey];
            $updatePayload = $values;
            unset($updatePayload[$businessKey]);

            // 7.2 — classify NEW vs EXISTING BEFORE the upsert by asking the LIVE
            // TARGET whether this natural key already exists. This is
            // authoritative and independent of ledger state, so a re-run (and the
            // same key seen in DB #2..#12 within one run) is counted as EXISTING,
            // never as a new insert. `updateOrInsert` then runs EITHER WAY so the
            // ONE row is updated in place — it never adds a duplicate row (R14.3).
            $alreadyPresent = $this->globalKeyExists($table, $businessKey, $keyValue);

            // A single bad row (NOT NULL target column the source left null,
            // type mismatch) must NOT abort the whole table — record it and
            // continue the batch (R13.5).
            try {
                $this->target->table($table)->updateOrInsert(
                    [$businessKey => $keyValue],
                    $updatePayload,
                );
            } catch (Throwable $e) {
                $this->recordError(
                    companyId: null,
                    key: sprintf('insert|%s|%s|%s', $descriptor->dbName, $table, (string) $keyValue),
                    message: sprintf('db=%s table=%s bk=%s: upsert failed: %s',
                        $descriptor->dbName, $table, (string) $keyValue, $e->getMessage()),
                );
                $errors++;
                $summary['errors']++;
                return;
            }

            // Record the GLOBAL natural key once (upsert by stable key → no
            // ledger row growth on re-run) for cross-DB within-run dedup +
            // provenance (R14.3/R14.5).
            $this->recordGlobalMasterKey($table, (string) $keyValue, $descriptor->dbName);

            $written++;
            $summary['globalsUpserted']++;
            if ($alreadyPresent) {
                $existing++;
                $summary['globalExisting']++;
            } else {
                $inserted++;
                $summary['globalInserted']++;
            }
        });

        $this->accumulatePerTable($summary, $table, TableCategory::GLOBAL_MASTER->value, $read, $inserted, $existing, $errors);
        $say('info', sprintf('    %s [GLOBAL]: read=%d upserted=%d (new=%d existing=%d) errors=%d (key=%s)',
            $table, $read, $written, $inserted, $existing, $errors, $businessKey));
    }

    // -------------------------------------------------------------------------
    // TENANT master copy (R11.3/R11.4/R11.6) — inject company_id, drop PK, ledger.
    // -------------------------------------------------------------------------

    /**
     * Copy a TENANT master: inject company_id (+ country_id where the target
     * column exists), drop the source PK (RowTransformer DROP_REIDENTITY), remap
     * audit, insert so SQL Server assigns a fresh identity, and record a
     * (company_id, business_key) -> new id ledger entry (R11.6). The ledger is
     * consulted BEFORE inserting so a re-run does not double-insert (R14.3).
     *
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary
     */
    private function copyTenantMaster(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TablePlan $plan,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        $table = $plan->table;
        $targetCols = $this->targetColumns($table);

        $businessKey = $this->businessKeyFor($table, $targetCols);
        if ($businessKey === null) {
            $this->recordNote(
                companyId: $tenant->companyId,
                key: sprintf('ambiguous_key|%s', $table),
                message: sprintf(
                    'db=%s TENANT table=%s: no determinable business key — table SKIPPED (not guess-inserted). '
                    .'Children needing its new id cannot be resolved until a key is defined.',
                    $descriptor->dbName, $table,
                ),
            );
            $summary['tablesSkipped']++;
            $summary['notes']++;
            $say('warn', sprintf('    %s: SKIP (ambiguous business key).', $table));
            return;
        }

        $read = 0;
        $written = 0;
        $existing = 0;
        $errors = 0;

        $this->eachSourceRow($pg, $table, $limit, function (array $src) use (
            $table, $plan, $tenant, $targetCols, $businessKey, $descriptor,
            &$read, &$written, &$existing, &$errors, &$summary
        ): void {
            $read++;

            // Resolve the business-key VALUE from the SOURCE row (via the column
            // map) BEFORE transform, so the ledger key is stable and independent
            // of the re-identity. The mapped row names the target business key.
            try {
                $mapped = $this->mapColumns($table, $src);
                $row = $this->transformer->transform($mapped, $plan, $tenant);
            } catch (RowTransformException $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('coerce|%s|%s|%d', $descriptor->dbName, $table, $read),
                    message: sprintf('db=%s table=%s col=%s: %s', $descriptor->dbName, $table, $e->column, $e->getMessage()),
                );
                $errors++;
                $summary['errors']++;
                return;
            }

            $values = $this->writeableValues($table, $row, $targetCols, $businessKey);

            $bkValue = $values[$businessKey] ?? null;
            if ($this->isBlank($bkValue)) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('no_bk|%s|%s|%d', $descriptor->dbName, $table, $read),
                    message: sprintf('db=%s table=%s: TENANT row has empty business key "%s" — skipped.',
                        $descriptor->dbName, $table, $businessKey),
                );
                $errors++;
                $summary['errors']++;
                return;
            }

            // Idempotency (R14.3/R14.5): if (company_id, business_key) is already
            // in the ledger this row was inserted on a previous run — SHORT-
            // CIRCUIT before any write, so the ledger value (the new identity) is
            // recorded exactly ONCE (on the first insert) and never overwritten on
            // a re-run. The SAME shared formatter ({@see tenantLedgerKey()}) builds
            // the key for BOTH this lookup and the record write, so a re-run always
            // hits the entry it wrote.
            $ledgerKey = $this->tenantLedgerKey($table, $tenant->companyId, (string) $bkValue);
            $existingId = $this->tenantLedgerLookup($ledgerKey);
            if ($existingId !== null) {
                $existing++;
                $summary['tenantExisting']++;
                return;
            }

            // Fresh insert — SQL Server assigns the identity (source PK already
            // dropped by RowTransformer DROP_REIDENTITY + identity column
            // stripped by writeableValues()). company_id is stamped. A single
            // bad row (NOT NULL target column, type mismatch) is recorded and
            // the batch CONTINUES (R13.5) rather than aborting the table.
            try {
                $this->target->table($table)->insert($values);
            } catch (Throwable $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('insert|%s|%s|%s', $descriptor->dbName, $table, (string) $bkValue),
                    message: sprintf('db=%s table=%s bk=%s: insert failed: %s',
                        $descriptor->dbName, $table, (string) $bkValue, $e->getMessage()),
                );
                $errors++;
                $summary['errors']++;
                return;
            }

            $newId = (int) $this->target->table($table)
                ->where('company_id', $tenant->companyId)
                ->where($businessKey, $bkValue)
                ->orderByDesc('id')
                ->value('id');

            // Record (company_id, business_key) -> new id (R11.6 / R14.3).
            $this->recordTenantMasterId($ledgerKey, $newId, $tenant->companyId, $descriptor->dbName, $table);

            $written++;
            $summary['tenantInserted']++;
        });

        $this->accumulatePerTable($summary, $table, TableCategory::TENANT_MASTER->value, $read, $written, $existing, $errors);
        $say('info', sprintf('    %s [TENANT]: read=%d inserted=%d existing=%d errors=%d (key=%s, company_id=%d)',
            $table, $read, $written, $existing, $errors, $businessKey, $tenant->companyId));
    }

    // -------------------------------------------------------------------------
    // Column mapping / business keys.
    // -------------------------------------------------------------------------

    /**
     * Map a raw SOURCE row to TARGET column names.
     *
     * When an explicit per-table COLUMN_MAP exists, only the mapped source
     * columns are kept (renamed to their target names) — this is the reliable
     * path for CI3's prefixed columns. Otherwise the row is passed through
     * unchanged and the later array_intersect_key keeps only same-named target
     * columns (the conservative fallback). The source id column is never mapped,
     * so it is dropped for tenant masters and ignored for global upserts.
     *
     * @param  array<string,mixed>  $src
     * @return array<string,mixed>
     */
    private function mapColumns(string $table, array $src): array
    {
        $map = self::COLUMN_MAP[$table] ?? null;
        if ($map === null) {
            return $src; // fallback: same-name intersect happens downstream.
        }

        $out = [];
        foreach ($map as $srcCol => $tgtCol) {
            if (array_key_exists($srcCol, $src)) {
                $out[$tgtCol] = $src[$srcCol];
            }
        }

        return $out;
    }

    /**
     * Build the write payload for a target table: keep only columns the live
     * target actually has (schema-drift safe — array_intersect_key), then STRIP
     * the target's IDENTITY column so SQL Server assigns the fresh identity
     * (never an explicit value — IDENTITY_INSERT is OFF). The identity column is
     * only removed when it is NOT the business key: a few CI3 masters use a
     * named identity PK that is distinct from the business key (e.g.
     * m_po_header_id vs m_po_header_number), while the business key is always a
     * natural column, never the identity — so this never drops the key.
     *
     * @param  array<string,mixed>  $row
     * @param  array<int,string>    $targetCols
     * @return array<string,mixed>
     */
    private function writeableValues(string $table, array $row, array $targetCols, string $businessKey): array
    {
        $values = array_intersect_key($row, array_flip($targetCols));

        $identity = $this->targetIdentityColumn($table);
        if ($identity !== null && $identity !== $businessKey) {
            unset($values[$identity]);
        }

        return $values;
    }

    /**
     * Resolve the business key (a TARGET column name) for a table.
     *
     * Precedence:
     *   1. the explicit BUSINESS_KEY map (authoritative), when that column
     *      actually exists on the live target;
     *   2. a derived key — the first "*_code"/"*_number" style unique-looking
     *      target column that is not id/company_id/country_id/audit;
     *   3. null — genuinely ambiguous, so the caller records a note and SKIPS.
     *
     * @param  array<int,string>  $targetCols
     */
    private function businessKeyFor(string $table, array $targetCols): ?string
    {
        $explicit = self::BUSINESS_KEY[$table] ?? null;
        if ($explicit !== null && in_array($explicit, $targetCols, true)) {
            return $explicit;
        }

        return $this->deriveBusinessKey($targetCols);
    }

    /**
     * Fallback business-key derivation: pick the first sensible unique-looking
     * non-id column (a "*_code" or "*_number"), excluding tenant/audit columns.
     * Returns null when nothing qualifies (caller skips, never guesses).
     *
     * @param  array<int,string>  $targetCols
     */
    private function deriveBusinessKey(array $targetCols): ?string
    {
        $exclude = ['id', 'company_id', 'country_id', 'estate_code',
            'created_by', 'created_at', 'updated_by', 'updated_at'];

        foreach ($targetCols as $col) {
            if (in_array($col, $exclude, true)) {
                continue;
            }
            if (str_ends_with($col, '_code') || str_ends_with($col, '_number')) {
                return $col;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Source reading (chunked).
    // -------------------------------------------------------------------------

    /**
     * Read the source table in chunks and invoke $handle for each row (as an
     * assoc array). Honours an optional per-table $limit for guarded smoke runs.
     *
     * @param  callable(array<string,mixed>):void  $handle
     */
    private function eachSourceRow(ConnectionInterface $pg, string $table, ?int $limit, callable $handle): void
    {
        $query = $pg->table($table);

        if ($limit !== null) {
            // Bounded guarded run: a single limited read (ordered for stability
            // is not possible without a known key, so take the natural order).
            foreach ($query->limit($limit)->get() as $rowObj) {
                $handle((array) $rowObj);
            }
            return;
        }

        // Full copy (R11.1): chunk by offset. Source masters are small; offset
        // chunking is adequate and avoids assuming a sortable key exists.
        $offset = 0;
        do {
            $rows = $query->offset($offset)->limit(self::READ_CHUNK)->get();
            foreach ($rows as $rowObj) {
                $handle((array) $rowObj);
            }
            $offset += self::READ_CHUNK;
        } while ($rows->count() === self::READ_CHUNK);
    }

    // -------------------------------------------------------------------------
    // Ledger helpers (mirror the RbacMigrator upsert-by-(entry_type,key_text)).
    // -------------------------------------------------------------------------

    /**
     * Build the tenant-master ledger key. The design calls for
     * (company_id, business_key) -> new id; we encode it as
     * "table|company_id|business_key" so the key is unique per table AND company
     * (two tables can share a business-key value; two companies can share one),
     * while company_id is ALSO stored in the ledger's company_id column.
     */
    private function tenantLedgerKey(string $table, int $companyId, string $businessKey): string
    {
        return sprintf('%s|%d|%s', $table, $companyId, $businessKey);
    }

    /** Look up an existing target id for a tenant-master ledger key, or null. */
    private function tenantLedgerLookup(string $ledgerKey): ?int
    {
        $value = $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_TENANT_MASTER_ID)
            ->where('key_text', $ledgerKey)
            ->value('value_text');

        return $value === null ? null : (int) $value;
    }

    /**
     * Record a (company_id, business_key) -> new target id entry (R11.6/R14.3).
     * Upserted by (entry_type, key_text) so a re-run adds NO ledger row. The
     * $ledgerKey MUST be the value returned by the shared {@see tenantLedgerKey()}
     * formatter (the same key the pre-insert lookup used). This is only ever
     * reached on the FIRST insert of a key (the caller short-circuits on a ledger
     * hit), so the recorded identity is written once and never overwritten on a
     * re-run (R14.5). table_name/source_db carry provenance for later child
     * copiers.
     */
    private function recordTenantMasterId(string $ledgerKey, int $newId, int $companyId, string $sourceDb, string $table): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_TENANT_MASTER_ID,
                'key_text'   => $ledgerKey,
            ],
            [
                'value_text' => (string) $newId,
                'company_id' => $companyId,
                'source_db'  => $sourceDb,
                'table_name' => $table,
                'message'    => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * Build the GLOBAL-master ledger key "table|business_key". Globals have no
     * company_id and are deduped across all 12 DBs, so the key is table-scoped
     * only (NOT company-scoped) — the SAME shared formatter is used for both the
     * record write and any lookup, so a re-run always hits (7.2).
     */
    private function globalLedgerKey(string $table, string $businessKey): string
    {
        return sprintf('%s|%s', $table, $businessKey);
    }

    /**
     * Whether a GLOBAL natural/business key already exists in the LIVE TARGET
     * table. Authoritative new-vs-existing classification (independent of ledger
     * state) used by {@see copyGlobalMaster()} so a re-run reports 0 new global
     * rows while still re-upserting in place (7.2 / R14.3).
     */
    private function globalKeyExists(string $table, string $businessKey, mixed $keyValue): bool
    {
        return $this->target->table($table)->where($businessKey, $keyValue)->exists();
    }

    /**
     * Record a GLOBAL natural key as upserted (entry_type 'global_master_key').
     * Upserted by (entry_type, key_text) so a re-run does NOT add a ledger row;
     * the created_at is preserved on re-run (only updated_at/provenance move) so
     * the entry still reflects when the key was first seen (7.2 / R14.5).
     */
    private function recordGlobalMasterKey(string $table, string $businessKey, string $sourceDb): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_GLOBAL_MASTER_KEY,
                'key_text'   => $this->globalLedgerKey($table, $businessKey),
            ],
            [
                'value_text' => '1',
                'source_db'  => $sourceDb,
                'table_name' => $table,
                'message'    => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * Record an error-ledger entry (entry_type 'error') — upserted by
     * (entry_type, key_text) so a re-run does not pile up duplicate rows (R13.5).
     */
    private function recordError(?int $companyId, string $key, string $message): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            ['entry_type' => self::LEDGER_ERROR, 'key_text' => $key],
            ['company_id' => $companyId, 'message' => $message, 'updated_at' => $now, 'created_at' => $now],
        );
    }

    /**
     * Record a non-fatal note (ambiguous key / skipped table / no target table).
     * Upserted by (entry_type, key_text) so a re-run does not pile up duplicates.
     */
    private function recordNote(?int $companyId, string $key, string $message): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            ['entry_type' => self::LEDGER_NOTE, 'key_text' => $key],
            ['company_id' => $companyId, 'message' => $message, 'updated_at' => $now, 'created_at' => $now],
        );
    }

    // -------------------------------------------------------------------------
    // Target schema helpers.
    // -------------------------------------------------------------------------

    /**
     * Live column listing of a target table (schema-drift safe writes), mirroring
     * RbacMigrator. Cached per table for the lifetime of this copier.
     *
     * @return array<int,string>
     */
    private function targetColumns(string $table): array
    {
        return $this->columnCache[$table] ??= $this->target->getSchemaBuilder()->getColumnListing($table);
    }

    /** @var array<string,array<int,string>> */
    private array $columnCache = [];

    /** Whether the live target has a table of this name. */
    private function targetTableExists(string $table): bool
    {
        return $this->target->getSchemaBuilder()->hasTable($table);
    }

    /**
     * The target table's IDENTITY (auto-increment) column name, or null when
     * the table has no identity column. SQL Server rejects an explicit value
     * for an identity column (IDENTITY_INSERT OFF), so this column is ALWAYS
     * stripped from the write payload — the fresh identity is assigned by the
     * engine. This covers both the conventional `id` column AND the CI3-style
     * named identity PKs the target kept (e.g. m_po_header.m_po_header_id,
     * m_order_header.m_order_header_id, m_deduction_code.deduction_code_id).
     * Cached per table.
     */
    private function targetIdentityColumn(string $table): ?string
    {
        if (array_key_exists($table, $this->identityCache)) {
            return $this->identityCache[$table];
        }

        $row = $this->target->selectOne(
            'SELECT COLUMN_NAME AS c FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = ? AND COLUMNPROPERTY(OBJECT_ID(QUOTENAME(TABLE_SCHEMA) + \'.\' + QUOTENAME(TABLE_NAME)), COLUMN_NAME, \'IsIdentity\') = 1',
            [$table]
        );

        return $this->identityCache[$table] = ($row->c ?? null);
    }

    /** @var array<string,?string> */
    private array $identityCache = [];

    /** A value that is null or an empty/whitespace string (unusable as a key). */
    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * Accumulate per-table counters into the summary (read/written/existing/errors).
     *
     * @param  array<string,mixed>  $summary
     */
    private function accumulatePerTable(
        array &$summary,
        string $table,
        string $category,
        int $read,
        int $written,
        int $existing,
        int $errors,
    ): void {
        $prev = $summary['perTable'][$table] ?? [
            'category' => $category, 'read' => 0, 'written' => 0, 'existing' => 0, 'errors' => 0,
        ];

        $summary['perTable'][$table] = [
            'category' => $category,
            'read'     => $prev['read'] + $read,
            'written'  => $prev['written'] + $written,
            'existing' => $prev['existing'] + $existing,
            'errors'   => $prev['errors'] + $errors,
        ];
    }
}
