<?php

namespace App\Services\Fusion;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * TransactionCopier — the transaction copy stage of estate-db-fusion (spec task
 * 8, requirements R12/R13/R14). Driven by the `fusion:copy-transactions` command
 * ({@see \App\Console\Commands\Fusion\CopyTransactionsCommand}).
 *
 * Spec authority:
 *   - design.md LLD-3 (table categories + per-category transform rules), LLD-4
 *     (per-table date-column registry + window), LLD-8 (ID / collision handling),
 *     LLD-9 (idempotency), LLD-10 copyWindowed() pseudocode, sequence diagram (d)
 *     "transaction windowed copy with header-child integrity".
 *   - requirements.md R12.1 (AnchorDate = max header date, window start =
 *     Anchor − N months), R12.2 (N default 2, configurable), R12.3 (filter each
 *     header by its own DateColumnRegistry column), R12.6 (carry VARCHAR id
 *     verbatim + inject company_id), R12.9 (no resolvable date column →
 *     full-copy + warn), R13.1/R13.2/R13.5 (cross-engine chunked batched insert,
 *     type coercion, record-and-continue), R14.1/R14.4 (idempotent — skip a
 *     header already present by id).
 *
 * ## SCOPE OF THIS TASK (8.1) — window computation + VARCHAR-id HEADER copy ONLY
 *
 * This task implements:
 *   1. Per-DB window (R12.1/R12.2): {@see computeWindow()} resolves each header's
 *      date column against the LIVE source (DateColumnRegistry::validateAgainst),
 *      takes MAX(date) across ALL the DB's header tables as the AnchorDate, and
 *      sets windowStart = AnchorDate − N months. N defaults to
 *      config('fusion.window_months', 2) and is overridable per run.
 *   2. VARCHAR-id header copy (R12.3/R12.6): for each header, filter rows by its
 *      OWN date column in [windowStart, AnchorDate], CARRY the VARCHAR id
 *      verbatim (RowTransformer CARRY_VARCHAR_ID), inject company_id (country_id
 *      only where the target column exists — driven by the taxonomy flag), remap
 *      audit, and write schema-drift-safely.
 *   3. No resolvable date column (R12.9): a header whose candidates all fail
 *      validation is FULL-copied (no date filter) with a logged WARNING.
 *   4. Idempotency guard (R14.1/R14.4 — formally hardened in 8.4): a header
 *      already present in the target by `id` is NOT re-inserted. Which header ids
 *      were loaded is recorded to the fusion_ledger (entry_type
 *      {@see LEDGER_HEADER_LOADED}, key "table|id") so 8.2 can select children by
 *      the loaded header set and 8.4 can be idempotent.
 *
 * ## SEAMS LEFT FOR LATER TASKS (deliberate extension points — mirrors how
 *    RbacMigrator left 5.5/5.6/5.8/5.10 seams):
 *   - {@see copyChildrenOf()}  — task 8.2 (bigint-id children): select children by
 *     the loaded header business-key set, drop the source bigint id for a fresh
 *     identity, preserve the VARCHAR business FK, never orphan. IMPLEMENTED.
 *   - {@see copyAttendanceWindow()} — task 8.3 (t_attendance special case):
 *     windowed by attendance_date, re-identity, record the natural key in the
 *     ledger. STUB here.
 *   - task 8.4 hardens idempotency (re-derive children from the loaded header set;
 *     chunked batched inserts; no pg_dump/pg_restore). The header-id ledger this
 *     task writes is the substrate 8.4 builds on.
 *
 * ## HEADER COVERAGE — which VARCHAR_ID_HEADER tables this task copies
 *
 * CI3 estate DBs name transaction columns with a PER-TABLE PREFIX the target
 * drops/renames (e.g. source `oph_created_date` → target `created_date` →
 * `created_at`; source `oph_id` → target `id`), exactly like the masters. This
 * copier uses an EXPLICIT per-table source→target {@see COLUMN_MAP} applied
 * before the RowTransformer, then ALSO merges any same-named source columns that
 * already match a live target column (the large shared "bunches_*"/status
 * overlaps need no enumeration).
 *
 * Of the 10 design headers, the source `id` is a genuine, estate-prefixed,
 * globally-unique app-generated VARCHAR for SEVEN of them — CONFIRMED by the
 * task 8.1 live probe (sagil_mei / rsm26):
 *     t_oph, t_cp, t_fdn, t_workdone, t_vra, t_coconut_oph, t_coconut_fdn
 * These are CARRIED verbatim (R12.6). The other THREE do NOT have a
 * carry-verbatim VARCHAR id and are DELIBERATELY DEFERRED (recorded as a note,
 * never guessed) because their id strategy needs dedicated analysis:
 *     - t_overtime    : source `overtime_id` is BIGINT (per-estate 1..N counter);
 *                       the target `t_overtime.id` is an nvarchar holding a plain
 *                       numeric string — carrying it verbatim would COLLIDE across
 *                       the 12 estates. Needs a re-identity / id-gen strategy.
 *     - tr_gi_header  : source `tr_gi_header_id` is BIGINT; the target `id` uses an
 *                       app-specific scheme (e.g. "UF_13777") that is NOT the
 *                       source number, and ~64% of source rows have a NULL
 *                       created timestamp. Needs dedicated id-mapping analysis.
 *     - tr_gr_header  : same divergence as tr_gi_header.
 * These three still participate in the AnchorDate computation wherever a date
 * column resolves, so the per-DB window stays correct; only their COPY is
 * deferred. {@see DEFERRED_HEADERS}.
 *
 * ## Schema-drift safe writes (mirrors MasterCopier / RbacMigrator)
 * Every write intersects the mapped row with the LIVE target column listing
 * (getColumnListing + array_intersect_key) so only columns the live table
 * actually has are written.
 *
 * ## Error handling (R13.5)
 * A per-row RowTransformException (type/coercion failure) is recorded to the
 * error ledger and the batch CONTINUES. A whole-table failure is recorded and
 * the NEXT table is attempted; a whole-DB failure is recorded and the NEXT DB is
 * attempted. The source connection is always closed in a finally.
 *
 * The behaviour lives in this service (unit/behaviour testable); the command is
 * a thin wrapper that wires options/output and calls {@see copy()}.
 */
class TransactionCopier
{
    /** Target connection name (SQL Server EPMS via ODBC). */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /**
     * fusion_ledger entry_type: a VARCHAR-id header row loaded into the target
     * (R14.1/R14.4). key_text = "table|id". Lets task 8.2 select children by the
     * loaded header set and lets task 8.4 make the copy idempotent. Upserted by
     * (entry_type, key_text) so a re-run adds no ledger row.
     */
    public const LEDGER_HEADER_LOADED = 'txn_header_loaded';

    /**
     * fusion_ledger entry_type: a bigint-id CHILD row loaded into the target
     * (task 8.2, R14.4). Children drop their source bigint id (R12.7), so they
     * cannot be keyed by id; the ledger key is the child NATURAL KEY
     * "table|company_id|parentFk|disc1|disc2|..." (see CHILD_NATURAL_KEY). A
     * re-run checks this entry (and the live natural-key existence) before
     * inserting, so no duplicate child is created. Upserted by (entry_type,
     * key_text) so a re-run adds no ledger row.
     */
    public const LEDGER_CHILD_LOADED = 'txn_child_loaded';

    /**
     * fusion_ledger entry_type: a t_attendance NATURAL KEY → new target id map
     * (task 8.3, R12.8). t_attendance drops its source bigint id for a fresh
     * identity, so later referrers cannot resolve the new attendance row by the
     * source id; they resolve it by the natural key
     * (company_id, employee_code, attendance_date). key_text is
     * "company_id|employee_code|attendance_date" and value_text is the NEW
     * target id SQL Server assigned. Upserted by (entry_type, key_text) so a
     * re-run neither duplicates the ledger row NOR the attendance row — the key
     * is the idempotency guard (R14.4).
     */
    public const LEDGER_ATTENDANCE_NK = 'attendance_natural_key';

    /** fusion_ledger entry_type: a skipped row / coercion failure (R13.5). */
    public const LEDGER_ERROR = 'error';

    /** fusion_ledger entry_type: a non-fatal note (deferred header / no date col). */
    public const LEDGER_NOTE = 'note';

    /** Insert chunk size for batched cross-engine inserts (R13.1). */
    public const INSERT_CHUNK = 200;

    /** Source read chunk size. */
    public const READ_CHUNK = 1000;

    /**
     * The VARCHAR-id headers this task copies — those whose source id is a
     * genuine estate-prefixed, globally-unique app-generated VARCHAR (CONFIRMED
     * by the task 8.1 live probe). Each maps to the SOURCE id column that becomes
     * the target `id` (carried verbatim, CARRY_VARCHAR_ID).
     *
     * @var array<string,string>  header table => source VARCHAR id column
     */
    private const HEADER_ID_COLUMN = [
        't_oph'         => 'oph_id',
        't_cp'          => 'cp_id',
        't_fdn'         => 'fdn_id',
        't_workdone'    => 'workdone_id',
        't_vra'         => 'vra_id',
        't_coconut_oph' => 'coconut_oph_id',
        't_coconut_fdn' => 'coconut_fdn_id',
    ];

    /**
     * Headers DELIBERATELY DEFERRED by task 8.1 because they lack a
     * carry-verbatim globally-unique VARCHAR id (see the class docblock). They
     * are recorded as a note and skipped for COPY, but still contribute to the
     * AnchorDate computation. A later task decides their id strategy.
     *
     * @var list<string>
     */
    private const DEFERRED_HEADERS = [
        't_overtime',
        'tr_gi_header',
        'tr_gr_header',
    ];

    /**
     * Explicit per-table SOURCE→TARGET column map for the headers whose source
     * (CI3, prefixed) column names do NOT match the target. Keys are SOURCE
     * columns, values are TARGET columns. The source VARCHAR id is mapped to the
     * target `id` (carried verbatim). Source audit columns are renamed to the
     * UNPREFIXED names the RowTransformer understands (created_date/created_time
     * and updated_date/updated_time become created_at/updated_at). Only columns
     * that need renaming are listed here; same-named source columns that already
     * match a target column (e.g. the large shared bunches, integration_status,
     * request_id, adjustment_status overlap) are merged automatically by
     * {@see mapColumns()}.
     *
     * Confirmed against the live source + target schemas by the task 8.1 probe.
     *
     * @var array<string,array<string,string>>
     */
    private const COLUMN_MAP = [
        't_oph' => [
            'oph_id'                => 'id',
            'oph_harvesting_method' => 'harvest_method',
            'oph_estate_code'       => 'estate_code',
            'oph_plant_code'        => 'plant_code',
            'oph_division_code'     => 'division_code',
            'oph_block_code'        => 'block_code',
            'oph_tph_code'          => 'tph_code',
            'oph_platform_no'       => 'platform_no',
            'oph_lat'               => 'lat',
            'oph_long'              => 'long',
            'oph_photo'             => 'photo',
            'oph_notes'             => 'notes',
            'oph_approved_by'       => 'approved_by',
            'oph_approved_by_name'  => 'approved_by_name',
            'oph_is_closed'         => 'is_closed',
            'oph_is_deleted'        => 'is_deleted',
            'oph_closing_is_approved'    => 'closing_is_approved',
            'oph_closing_is_approved_by' => 'closing_approved_by',
            'oph_closing_approved_timestamp' => 'closing_approved_at',
            'vra_order_number'      => 'customer_code', // best-effort; dropped if absent downstream
            'REMARK'                => 'remark',
            // approved date+time → approved_at (combined by a small pre-pass below)
            'oph_approved_date'     => '__approved_date',
            'oph_approved_time'     => '__approved_time',
            // audit → RowTransformer unprefixed names
            'oph_created_by'        => 'created_by',
            'oph_created_date'      => 'created_date',
            'oph_created_time'      => 'created_time',
            'oph_updated_by'        => 'updated_by',
            'oph_updated_date'      => 'updated_date',
            'oph_updated_time'      => 'updated_time',
        ],
        't_cp' => [
            'cp_id'                 => 'id',
            'cp_estate_code'        => 'estate_code',
            'cp_division_code'      => 'division_code',
            'cp_license_number'     => 'license_number',
            'cp_license_number2'    => 'license_number2',
            'cp_seal_code'          => 'seal_code',
            'cp_receiving_point_code' => 'receiving_point_code',
            'cp_delivery_note'      => 'delivery_note',
            'cp_lat'                => 'lat',
            'cp_long'               => 'long',
            'cp_photo'              => 'photo',
            'cp_total_bunches'      => 'total_bunches',
            'cp_total_oph'          => 'total_oph',
            'cp_total_loose_fruit'  => 'total_loose_fruit',
            'cp_estimate_tonnage'   => 'estimate_tonnage',
            'cp_actual_tonnage'     => 'actual_tonnage',
            'cp_bruto'              => 'bruto',
            'cp_tarra'              => 'tarra',
            'cp_bin_number'         => 'bin_number',
            'cp_kerani_kirim_employee_code' => 'kerani_kirim_emp_code',
            'cp_kerani_kirim_employee_name' => 'kerani_kirim_emp_name',
            'cp_bunches_wet'        => 'bunches_wet',
            'cp_bunches_ripe'       => 'bunches_ripe',
            'cp_bunches_overripe'   => 'bunches_overripe',
            'cp_bunches_underripe'  => 'bunches_underripe',
            'cp_bunches_unripe'     => 'bunches_unripe',
            'cp_bunches_rotten'     => 'bunches_rotten',
            'cp_bunches_long_stalk' => 'bunches_long_stalk',
            'cp_bunches_empty'      => 'bunches_empty',
            'cp_bunches_dirty'      => 'bunches_dirty',
            'cp_bunches_unfresh'    => 'bunches_unfresh',
            'cp_bunches_old'        => 'bunches_old',
            'cp_bunches_pest_damaged_old' => 'bunches_pest_damaged_old',
            'cp_bunches_pest_damaged_new' => 'bunches_pest_damaged_new',
            'cp_bunches_diseased'   => 'bunches_diseased',
            'cp_bunches_total'      => 'bunches_total',
            'cp_transporter'        => 'transporter',
            'cp_transporter2'       => 'transporter2',
            'cp_vendor_code'        => 'vendor_code',
            'cp_vendor_name'        => 'vendor_name',
            'cp_license_number_vendor'  => 'license_number_vendor',
            'cp_license_number_vendor2' => 'license_number_vendor2',
            'cp_sailing_date'       => 'sailing_date',
            'cp_ship_flag'          => 'ship_flag',
            'cp_cable_way'          => 'cable_way',
            'cp_is_closed'          => 'is_closed',
            'cp_is_deleted'         => 'is_deleted',
            'cp_closing_is_approved'    => 'closing_is_approved',
            'cp_closing_is_approved_by' => 'closing_approved_by',
            'cp_closing_approved_timestamp' => 'closing_approved_at',
            'REMARK'                => 'remark',
            'cp_created_by'         => 'created_by',
            'cp_created_date'       => 'created_date',
            'cp_created_time'       => 'created_time',
            'cp_updated_by'         => 'updated_by',
            'cp_updated_date'       => 'updated_date',
            'cp_updated_time'       => 'updated_time',
        ],
        't_fdn' => [
            'fdn_id'                => 'id',
            'fdn_estate_code'       => 'estate_code',
            'fdn_division_code'     => 'division_code',
            'fdn_license_number'    => 'license_number',
            'fdn_license_number2'   => 'license_number2',
            'fdn_seal_code'         => 'seal_code',
            'fdn_deliver_to_code'   => 'deliver_to_code',
            'fdn_deliver_to_name'   => 'deliver_to_name',
            'fdn_delivery_note'     => 'delivery_note',
            'fdn_lat'               => 'lat',
            'fdn_long'              => 'long',
            'fdn_photo'             => 'photo',
            'fdn_total_bunches'     => 'total_bunches',
            'fdn_total_oph'         => 'total_oph',
            'fdn_total_loose_fruit' => 'total_loose_fruit',
            'fdn_estimate_tonnage'  => 'estimate_tonnage',
            'fdn_actual_tonnage'    => 'actual_tonnage',
            'fdn_bruto'             => 'bruto',
            'fdn_tarra'             => 'tarra',
            'fdn_receiving_point_code' => 'receiving_point_code',
            'fdn_kerani_kirim_employee_code' => 'kerani_kirim_emp_code',
            'fdn_kerani_kirim_employee_name' => 'kerani_kirim_emp_name',
            'fdn_vendor_name'       => 'vendor_name',
            'fdn_license_number_vendor'  => 'license_number_vendor',
            'fdn_license_number_vendor2' => 'license_number_vendor2',
            'fdn_transporter'       => 'transporter',
            'fdn_transporter2'      => 'transporter2',
            'fdn_ship_flag'         => 'ship_flag',
            'fdn_cable_way'         => 'cable_way',
            'fdn_is_closed'         => 'is_closed',
            'fdn_is_deleted'        => 'is_deleted',
            'fdn_closing_is_approved'    => 'closing_is_approved',
            'fdn_closing_is_approved_by' => 'closing_approved_by',
            'fdn_closing_approved_timestamp' => 'closing_approved_at',
            'REMARK'                => 'remark',
            'fdn_created_by'        => 'created_by',
            'fdn_created_date'      => 'created_date',
            'fdn_created_time'      => 'created_time',
            'fdn_updated_by'        => 'updated_by',
            'fdn_updated_date'      => 'updated_date',
            'fdn_updated_time'      => 'updated_time',
        ],
        't_workdone' => [
            'workdone_id'            => 'id',
            'workdone_estate_code'   => 'estate_code',
            'workdone_plant_code'    => 'plant_code',
            'workdone_division_code' => 'division_code',
            'workdone_activity_code' => 'activity_code',
            'workdone_activity_name' => 'activity_name',
            'workdone_activity_uom'  => 'activity_uom',
            'workdone_block_code'    => 'block_code',
            'workdone_order_number'  => 'order_number',
            'workdone_auc_number'    => 'auc_number',
            'workdone_cost_center'   => 'cost_center',
            'workdone_wbs_code'      => 'wbs_code',
            'workdone_wbs_name'      => 'wbs_name',
            'workdone_mandor_employee_code' => 'mandor_employee_code',
            'workdone_mandor_employee_name' => 'mandor_employee_name',
            'workdone_employee_code' => 'employee_code',
            'workdone_employee_name' => 'employee_name',
            'workdone_mandays'       => 'mandays',
            'workdone_manday_old'    => 'manday',
            'workdone_qty'           => 'qty',
            'workdone_target_qty'    => 'target_qty',
            'workdone_flexrate'      => 'flexrate',
            'workdone_start_time'    => 'start_time',
            'workdone_end_time'      => 'end_time',
            'workdone_duration'      => 'duration',
            'workdone_description'   => 'description',
            'workdone_customer_code' => 'customer_code',
            'workdone_is_planned'    => 'is_planned',
            'workdone_is_approved'   => 'is_approved',
            'workdone_approved_by'   => 'approved_by',
            'workdone_approved_by_name' => 'approved_by_name',
            'workdone_is_closed'     => 'is_closed',
            'workdone_block_status'  => 'block_status',
            'workdone_closing_is_approved'    => 'closing_is_approved',
            'workdone_closing_is_approved_by' => 'closing_approved_by',
            'workdone_closing_approved_timestamp' => 'closing_approved_at',
            'REMARK'                 => 'remark',
            'workdone_approved_date' => '__approved_date',
            'workdone_approved_time' => '__approved_time',
            'workdone_created_by'    => 'created_by',
            'workdone_created_date'  => 'created_date',
            'workdone_created_time'  => 'created_time',
            'workdone_updated_by'    => 'updated_by',
            'workdone_updated_date'  => 'updated_date',
            'workdone_updated_time'  => 'updated_time',
        ],
        't_vra' => [
            'vra_id'                => 'id',
            'vra_posting_date'      => 'vra_date', // target vra_date = the VRA posting date
            'vra_work_center'       => 'equipment_code', // best-effort; dropped if absent
            'vra_measurement'       => 'reading_value',
            'vra_confirm_text'      => 'confirmation_text',
            'vra_is_approved'       => 'is_approved',
            'vra_approved_by'       => 'approved_by',
            'REMARK'                => 'remark',
            'vra_approved_date'     => '__approved_date',
            'vra_approved_time'     => '__approved_time',
            'vra_created_by'        => 'created_by',
            'vra_created_date'      => 'created_date',
            'vra_created_time'      => 'created_time',
            'vra_updated_by'        => 'updated_by',
            'vra_updated_date'      => 'updated_date',
            'vra_updated_time'      => 'updated_time',
        ],
        't_coconut_oph' => [
            'coconut_oph_id'            => 'id',
            'coconut_oph_plant_code'    => 'plant_code',
            'coconut_oph_estate_code'   => 'estate_code',
            'coconut_oph_division_code' => 'division_code',
            'coconut_oph_block_code'    => 'block_code',
            'coconut_oph_tph_code'      => 'tph_code',
            'coconut_oph_card_id'       => 'oph_card_id',
            'coconut_oph_gang_code'     => 'gang_code',
            'coconut_oph_gang_name'     => 'gang_name',
            'coconut_oph_checker_employee_code' => 'checker_employee_code',
            'coconut_oph_checker_employee_name' => 'checker_employee_name',
            'coconut_oph_notes'         => 'notes',
            'coconut_oph_lat'           => 'lat',
            'coconut_oph_long'          => 'long',
            'coconut_oph_photo'         => 'photo',
            'coconut_oph_nuts_total'    => 'nuts_total',
            'coconut_oph_is_planned'    => 'is_planned',
            'coconut_oph_is_approved'   => 'is_approved',
            'coconut_oph_approved_by'   => 'approved_by',
            'coconut_oph_approved_by_name' => 'approved_by_name',
            'coconut_oph_approved_timestamp' => 'approved_at',
            'coconut_oph_is_closed'     => 'is_closed',
            'coconut_oph_closing_is_approved'    => 'closing_is_approved',
            'coconut_oph_closing_approved_by'    => 'closing_approved_by',
            'coconut_oph_closing_approved_timestamp' => 'closing_approved_at',
            'coconut_oph_is_deleted'    => 'is_deleted',
            'REMARK'                    => 'remark',
            'coconut_oph_created_by'    => 'created_by',
            'coconut_oph_created_date'  => 'created_date',
            'coconut_oph_created_time'  => 'created_time',
            'coconut_oph_updated_by'    => 'updated_by',
            'coconut_oph_updated_date'  => 'updated_date',
            'coconut_oph_updated_time'  => 'updated_time',
        ],
        't_coconut_fdn' => [
            'coconut_fdn_id'            => 'id',
            'coconut_fdn_estate_code'   => 'estate_code',
            'coconut_fdn_division_code' => 'division_code',
            'coconut_fdn_sales_order'   => 'sales_order_no',
            'coconut_fdn_sales_order_item_code' => 'sales_order_item',
            'coconut_fdn_receiving_point_code' => 'receiving_point_code',
            'coconut_fdn_license_number' => 'license_number',
            'coconut_fdn_driver_name'   => 'driver_name',
            'coconut_fdn_vehicle_vendor_code' => 'vehicle_vendor_code',
            'coconut_fdn_kerani_kirim_employee_code' => 'kerani_kirim_emp_code',
            'coconut_fdn_kerani_kirim_employee_name' => 'kerani_kirim_emp_name',
            'coconut_fdn_delivery_note' => 'delivery_note',
            'coconut_fdn_card_id'       => 'fdn_card_id',
            'coconut_fdn_lat'           => 'lat',
            'coconut_fdn_long'          => 'long',
            'coconut_fdn_photo'         => 'photo',
            'coconut_fdn_total_oph'     => 'total_oph',
            'coconut_fdn_bruto'         => 'bruto',
            'coconut_fdn_tarra'         => 'tarra',
            'coconut_fdn_actual_tonnage' => 'actual_tonnage',
            'coconut_fdn_total_customer_qty' => 'total_customer_qty',
            'coconut_fdn_destination'   => 'destination',
            'coconut_fdn_is_nursery'    => 'is_nursery',
            'coconut_fdn_is_stock'      => 'is_stock',
            'coconut_fdn_is_closed'     => 'is_closed',
            'coconut_fdn_closing_is_approved'    => 'closing_is_approved',
            'coconut_fdn_closing_is_approved_by' => 'closing_approved_by',
            'coconut_fdn_closing_approved_timestamp' => 'closing_approved_at',
            'coconut_fdn_is_deleted'    => 'is_deleted',
            'REMARK'                    => 'remark',
            'coconut_fdn_created_by'    => 'created_by',
            'coconut_fdn_created_date'  => 'created_date',
            'coconut_fdn_created_time'  => 'created_time',
            'coconut_fdn_updated_by'    => 'updated_by',
            'coconut_fdn_updated_date'  => 'updated_date',
            'coconut_fdn_updated_time'  => 'updated_time',
        ],
    ];

    /**
     * In-scope bigint-id CHILD tables, grouped by the VARCHAR-id parent header
     * they belong to (task 8.2). Only children whose PARENT header is copied by
     * task 8.1 (the {@see HEADER_ID_COLUMN} / carry-verbatim set) are listed, so
     * children of the deferred headers (t_overtime → t_overtime_detail,
     * tr_gi_header → tr_gi_detail, tr_gr_header → tr_gr_detail) are NOT here —
     * their parents carry no loaded header set, and (for GI/GR) their source FK
     * is a BIGINT, not a VARCHAR business key, so they cannot be re-linked by the
     * business-key rule (R13.4). A note is recorded when such a child is present
     * in the source but out of scope.
     *
     * CONFIRMED by the task 8.2 live probe (rsm26 + sagil_mei): t_oph_detail,
     * t_workdone_detail, t_overtime_detail and t_vra_detail are ABSENT in the CI3
     * sources (the OPH person split lives in t_oph_persons; workdone has only
     * t_workdone_material), so they never appear.
     *
     * @var array<string,list<string>>  parent header => its in-scope child tables
     */
    private const CHILDREN_OF = [
        't_oph'         => ['t_oph_persons', 't_harvesting_deduction'],
        't_cp'          => ['t_cp_detail', 't_cp_loader'],
        't_fdn'         => ['t_fdn_detail', 't_fdn_loader'],
        't_workdone'    => ['t_workdone_material'],
        't_coconut_oph' => ['t_coconut_oph_detail', 't_coconut_oph_persons'],
        't_coconut_fdn' => ['t_coconut_fdn_detail'],
        // t_vra has no child in the CI3 sources (t_vra_detail absent).
    ];

    /**
     * The SOURCE VARCHAR business-FK column on each in-scope child that points at
     * its parent header's VARCHAR id (R12.4). The child is selected by this
     * column restricted to the LOADED header-id set (never by date), and the
     * column is PRESERVED verbatim into the target so the app-level join by
     * business key + company_id still holds (R12.7/R13.4). CONFIRMED by the task
     * 8.2 live probe. Note t_harvesting_deduction references t_oph through
     * `harvesting_deduction_oph_id` (its parent is OPH, not a table of its own).
     *
     * @var array<string,string>  child table => source VARCHAR parent-FK column
     */
    private const CHILD_PARENT_FK = [
        't_oph_persons'         => 'oph_id',
        't_harvesting_deduction' => 'harvesting_deduction_oph_id',
        't_cp_detail'           => 'cp_id',
        't_cp_loader'           => 'cp_id',
        't_fdn_detail'          => 'fdn_id',
        't_fdn_loader'          => 'fdn_id',
        't_workdone_material'   => 'workdone_id',
        't_coconut_oph_detail'  => 'coconut_oph_id',
        't_coconut_oph_persons' => 'coconut_oph_id',
        't_coconut_fdn_detail'  => 'coconut_fdn_id',
    ];

    /**
     * The child NATURAL KEY used for idempotency (R14.4). Because children drop
     * the source bigint id (R12.7), a re-run cannot dedupe by id; it dedupes by
     * (company_id, parent business FK, + the child's own discriminator columns).
     * These are TARGET column names (resolved after {@see mapColumns()}); the
     * parent-FK target column is included implicitly by the copier, so this list
     * is only the EXTRA discriminator columns beyond the parent FK. Chosen to be
     * the smallest set that uniquely identifies a child line within its parent.
     * CONFIRMED by the task 8.2 live probe.
     *
     *   t_oph_persons         : + employee_code, person_type (an employee may
     *                           appear as harvester AND loose-fruit picker).
     *   t_harvesting_deduction: + deduction_code, deduction_date (one deduction
     *                           code per oph per date).
     *   t_cp_detail           : + oph_id, detail_type (one OPH delivery line,
     *                           typed).
     *   t_cp_loader           : + employee_code, loader_type.
     *   t_fdn_detail          : + oph_id.
     *   t_fdn_loader          : + employee_code, loader_type.
     *   t_workdone_material   : + material_code.
     *   t_coconut_oph_detail  : + material_code.
     *   t_coconut_oph_persons : + employee_code.
     *   t_coconut_fdn_detail  : + oph_id (the delivered coconut-OPH line).
     *
     * @var array<string,list<string>>  child table => extra discriminator (target) columns
     */
    private const CHILD_NATURAL_KEY = [
        't_oph_persons'         => ['employee_code', 'person_type'],
        't_harvesting_deduction' => ['deduction_code', 'deduction_date'],
        't_cp_detail'           => ['oph_id', 'detail_type'],
        't_cp_loader'           => ['employee_code', 'loader_type'],
        't_fdn_detail'          => ['oph_id'],
        't_fdn_loader'          => ['employee_code', 'loader_type'],
        't_workdone_material'   => ['material_code'],
        't_coconut_oph_detail'  => ['material_code'],
        't_coconut_oph_persons' => ['employee_code'],
        't_coconut_fdn_detail'  => ['oph_id'],
    ];

    /**
     * Explicit per-CHILD SOURCE→TARGET column map (same approach as the header
     * {@see COLUMN_MAP}). Keys are SOURCE (CI3, prefixed) columns, values are
     * TARGET columns. The source bigint id is NOT listed (it is dropped,
     * DROP_REIDENTITY). The VARCHAR parent FK is mapped to its target name
     * (usually identical, but harvesting_deduction renames it to `oph_id`).
     * Same-named source columns that already match a live target column (e.g.
     * `integration_status`, `request_id`, `remark` via the REMARK rename) are
     * merged automatically by {@see mapColumns()}; only columns that need
     * renaming are listed. CONFIRMED against the live source + target schemas by
     * the task 8.2 probe.
     *
     * @var array<string,array<string,string>>
     */
    private const CHILD_COLUMN_MAP = [
        't_oph_persons' => [
            'oph_person_employee_code'  => 'employee_code',
            'oph_person_employee_name'  => 'employee_name',
            'oph_person_percentage'     => 'percentage',
            'oph_person_type'           => 'person_type',
            // oph_person_lf_percentage has no target column — dropped by the
            // schema-drift-safe write; oph_id passes through same-named.
        ],
        't_harvesting_deduction' => [
            'harvesting_deduction_oph_id'       => 'oph_id',
            'harvesting_deduction_date'         => 'deduction_date',
            'harvesting_deduction_code'         => 'deduction_code',
            'harvesting_deduction_description'  => 'deduction_description',
            'harvesting_deduction_qty'          => 'deduction_qty',
            'REMARK'                            => 'remark',
            'harvesting_deduction_created_by'   => 'created_by',
            'harvesting_deduction_created_date' => 'created_date',
            'harvesting_deduction_created_time' => 'created_time',
            'harvesting_deduction_updated_by'   => 'updated_by',
            'harvesting_deduction_updated_date' => 'updated_date',
            'harvesting_deduction_updated_time' => 'updated_time',
            // harvesting_deduction_is_closed has no target column — dropped.
        ],
        't_cp_detail' => [
            'cp_oph_id'                    => 'oph_id',
            'cp_oph_bunches_delivered'     => 'bunches_delivered',
            'cp_oph_loose_fruit_delivered' => 'loose_fruit_delivered',
            'cp_oph_block_code'            => 'oph_block_code',
            'cp_oph_tph_code'              => 'oph_tph_code',
            'cp_oph_card_id'               => 'oph_card_id',
            'cp_oph_platform_no'           => 'oph_platform_no',
            'cp_detail_type'               => 'detail_type',
            'REMARK'                       => 'remark',
            // cp_id, integration_status pass through same-named; platform_no
            // (jsonb) passes through same-named if the target has it.
        ],
        't_cp_loader' => [
            'cp_loader_employee_code' => 'employee_code',
            'cp_loader_employee_name' => 'employee_name',
            'cp_loader_vendor'        => 'vendor_code',
            'cp_loader_transporter'   => 'transporter',
            'cp_loader_percentage'    => 'percentage',
            'cp_loader_type'          => 'loader_type',
            // cp_id, integration_status, request_id pass through same-named.
        ],
        't_fdn_detail' => [
            'fdn_oph_id'                    => 'oph_id',
            'fdn_oph_bunches_delivered'     => 'bunches_delivered',
            'fdn_oph_loose_fruit_delivered' => 'loose_fruit_delivered',
            'fdn_oph_block_code'            => 'oph_block_code',
            'fdn_oph_tph_code'              => 'oph_tph_code',
            'fdn_oph_card_id'               => 'oph_card_id',
            'fdn_detail_type'               => 'detail_type', // absent in source → dropped
            'REMARK'                        => 'remark',
            // fdn_id, integration_status pass through same-named.
        ],
        't_fdn_loader' => [
            'fdn_loader_employee_code' => 'employee_code',
            'fdn_loader_employee_name' => 'employee_name',
            'fdn_loader_vendor'        => 'vendor_code',
            'fdn_loader_transporter'   => 'transporter',
            'fdn_loader_percentage'    => 'percentage',
            'fdn_loader_type'          => 'loader_type',
            // fdn_id, integration_status, request_id pass through same-named.
        ],
        't_workdone_material' => [
            'workdone_material_code' => 'material_code',
            'workdone_material_name' => 'material_name',
            'workdone_material_uom'  => 'material_uom',
            'workdone_material_qty'  => 'qty',
            // workdone_id passes through same-named.
        ],
        't_coconut_oph_detail' => [
            'coconut_oph_detail_material_code' => 'material_code',
            'coconut_oph_detail_material_name' => 'material_name',
            'coconut_oph_detail_customer_nut_qty' => 'customer_nut_qty',
            'coconut_oph_detail_harvesting_deduction_is_locked' => 'is_locked',
            'coconut_oph_detail_closing_is_approved' => 'closing_is_approved',
            'coconut_oph_detail_closing_approved_by' => 'closing_approved_by',
            'coconut_oph_detail_closing_approved_timestamp' => 'closing_approved_at',
            'coconut_oph_detail_is_deleted'    => 'is_deleted',
            'REMARK'                           => 'remark',
            // coconut_oph_id, integration_status, adjustment_status, request_id
            // pass through same-named.
        ],
        't_coconut_oph_persons' => [
            'coconut_oph_person_employee_code' => 'employee_code',
            'coconut_oph_person_employee_name' => 'employee_name',
            'coconut_oph_person_activity_type' => 'activity_type',
            'REMARK'                           => 'remark',
            // coconut_oph_id, integration_status pass through same-named.
        ],
        't_coconut_fdn_detail' => [
            'coconut_fdn_oph_id'      => 'coconut_oph_id',
            'coconut_fdn_oph_card_id' => 'coconut_oph_card_id',
            'coconut_fdn_oph_total_customer_nut_qty' => 'total_customer_nut_qty',
            'REMARK'                  => 'remark',
            // coconut_fdn_id, integration_status pass through same-named.
        ],
    ];

    /**
     * Explicit SOURCE→TARGET column map for the t_attendance special case (task
     * 8.3). Keys are SOURCE (CI3, prefixed) columns, values are TARGET columns.
     * The source bigint id `attendance_id` is NOT listed — it is DROPPED
     * (DROP_REIDENTITY) so SQL Server assigns a fresh identity (R12.8). Audit
     * columns are renamed to the unprefixed names the RowTransformer understands
     * (created_date/created_time + updated_date/updated_time → created_at/
     * updated_at). Same-named source columns that already match a live target
     * column (`attendance_date`, `attendance_code`, `attendance_desc`,
     * `integration_status`, `request_id`, `adjustment_status`, and `remark` via
     * the REMARK rename) are merged automatically by {@see mapColumns()}; only
     * columns that need renaming are listed.
     *
     * CONFIRMED against the live source + target schemas by the task 8.3 probe
     * (sagil_mei source → EPMS target):
     *   SOURCE t_attendance: attendance_id(bigint), attendance_date(date),
     *     attendance_mandor_employee_code/_name, attendance_employee_code/_name,
     *     attendance_code, attendance_desc, attendance_work_status,
     *     attendance_is_closed, attendance_created_by/_date/_time,
     *     attendance_updated_by/_date/_time, request_id, integration_status,
     *     REMARK, attendance_closing_is_approved(_by),
     *     attendance_closing_approved_timestamp, attendance_gang_allotment_code,
     *     adjustment_status.
     *   TARGET t_attendance: id(bigint identity), company_id, attendance_date,
     *     mandor_employee_code/_name, employee_code/_name, attendance_code,
     *     attendance_desc, work_status, gang_allotment_code, is_closed,
     *     closing_is_approved, closing_approved_by, closing_approved_at,
     *     adjustment_status, integration_status, remark, request_id,
     *     created_by, created_at, updated_by, updated_at.
     *
     * @var array<string,string>
     */
    private const ATTENDANCE_COLUMN_MAP = [
        'attendance_mandor_employee_code'       => 'mandor_employee_code',
        'attendance_mandor_employee_name'       => 'mandor_employee_name',
        'attendance_employee_code'              => 'employee_code',
        'attendance_employee_name'              => 'employee_name',
        'attendance_work_status'                => 'work_status',
        'attendance_is_closed'                  => 'is_closed',
        'attendance_gang_allotment_code'        => 'gang_allotment_code',
        'attendance_closing_is_approved'        => 'closing_is_approved',
        'attendance_closing_is_approved_by'     => 'closing_approved_by',
        'attendance_closing_approved_timestamp' => 'closing_approved_at',
        'REMARK'                                => 'remark',
        'attendance_created_by'                 => 'created_by',
        'attendance_created_date'               => 'created_date',
        'attendance_created_time'               => 'created_time',
        'attendance_updated_by'                 => 'updated_by',
        'attendance_updated_date'               => 'updated_date',
        'attendance_updated_time'               => 'updated_time',
        // attendance_date, attendance_code, attendance_desc, integration_status,
        // request_id, adjustment_status pass through same-named (merged by
        // mapColumns). attendance_id is DROPPED (DROP_REIDENTITY).
    ];

    /** SOURCE column holding the attendance natural-key employee code. */
    private const ATTENDANCE_EMPLOYEE_CODE_SRC = 'attendance_employee_code';

    /** SOURCE column holding the attendance natural-key date. */
    private const ATTENDANCE_DATE_SRC = 'attendance_date';

    private readonly ConnectionInterface $target;

    /** @var array<string,array<int,string>> */
    private array $columnCache = [];

    /**
     * Per-(db, parent header) set of header ids LOADED this run, captured in
     * memory during {@see copyHeader()} so {@see copyChildrenOf()} can select
     * children by the loaded set without re-reading the ledger for every parent
     * (R12.4). Keyed "db|table" → list of header id strings. Falls back to the
     * ledger when a parent produced no in-memory set (e.g. a fully pre-existing
     * header set from a prior run).
     *
     * @var array<string,array<int,string>>
     */
    private array $loadedHeaderIds = [];

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
     * Copy windowed transaction headers across the 12 source DBs and return a
     * summary.
     *
     * @param  callable(string $level, string $message):void|null  $progress
     * @param  array{only_db?: ?string, limit?: ?int, months?: ?int}  $options
     *     - only_db: restrict to a single source db_name (guarded run).
     *     - limit:   cap source rows read PER header table (guarded smoke run).
     *     - months:  override N (window months); falls back to config default.
     * @return array{
     *     databases:int, headersProcessed:int, headersDeferred:int,
     *     headersFullCopied:int, inserted:int, existing:int, errors:int, notes:int,
     *     childrenRead:int, childrenInserted:int, childrenExisting:int, childrenErrors:int,
     *     attendanceRead:int, attendanceInserted:int, attendanceExisting:int, attendanceErrors:int,
     *     windows:array<string,array{anchor:?string,windowStart:?string,months:int}>,
     *     perTable:array<string,array{db:string,dateColumn:?string,read:int,written:int,existing:int,errors:int,fullCopy:bool}>,
     *     perChildTable:array<string,array{db:string,parent:string,parentFk:string,read:int,inserted:int,existing:int,errors:int}>
     * }
     */
    public function copy(?callable $progress = null, array $options = []): array
    {
        $say = $progress ?? static fn (string $level, string $message) => null;

        $onlyDb = $options['only_db'] ?? null;
        $limit = $options['limit'] ?? null;
        $months = $this->resolveMonths($options['months'] ?? null);

        $taxonomy = $this->taxonomy ?? new TableTaxonomy($this->target);

        $summary = [
            'databases'         => 0,
            'headersProcessed'  => 0,
            'headersDeferred'   => 0,
            'headersFullCopied' => 0,
            'inserted'          => 0,
            'existing'          => 0,
            'errors'            => 0,
            'notes'             => 0,
            // Child (task 8.2) counters.
            'childrenRead'      => 0,
            'childrenInserted'  => 0,
            'childrenExisting'  => 0,
            'childrenErrors'    => 0,
            // t_attendance (task 8.3) counters.
            'attendanceRead'     => 0,
            'attendanceInserted' => 0,
            'attendanceExisting' => 0,
            'attendanceErrors'   => 0,
            'windows'           => [],
            'perTable'          => [],
            'perChildTable'     => [],
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

            $say('info', sprintf('Copying transactions from %s (estate %s, company %s, N=%d months)...',
                $descriptor->dbName, $descriptor->estateCode, $descriptor->companyCode, $months));
            $summary['databases']++;

            try {
                $this->copyOneDatabase($descriptor, $tenant, $taxonomy, $months, $limit, $say, $summary);
            } catch (Throwable $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: 'db_failure|'.$descriptor->dbName,
                    message: sprintf('db=%s: transaction copy failed: %s', $descriptor->dbName, $e->getMessage()),
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
     * Copy the windowed headers of a single source DB.
     *
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary mutated in place.
     */
    private function copyOneDatabase(
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TableTaxonomy $taxonomy,
        int $months,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        $pg = SourceConnection::for($descriptor->dbName);

        // Fresh in-memory loaded-header set for THIS db (task 8.2 child select).
        $this->loadedHeaderIds = [];

        // Which VARCHAR-id headers actually exist in THIS source DB?
        $presentHeaders = $this->presentHeaders($pg);

        // ---- 1. Per-DB window (R12.1/R12.2) ----------------------------------
        $window = $this->computeWindow($pg, $presentHeaders, $months);
        $summary['windows'][$descriptor->dbName] = [
            'anchor'      => $window['anchor'],
            'windowStart' => $window['windowStart'],
            'months'      => $months,
        ];
        $say('info', sprintf('  Window: anchor=%s windowStart=%s (N=%d)',
            $window['anchor'] ?? 'NONE', $window['windowStart'] ?? 'NONE', $months));

        // ---- 2. Copy each present header -------------------------------------
        foreach ($presentHeaders as $table) {
            // Defer the headers whose id strategy is unresolved (never guessed).
            if (in_array($table, self::DEFERRED_HEADERS, true)) {
                $this->recordNote(
                    companyId: $tenant->companyId,
                    key: sprintf('deferred_header|%s|%s', $descriptor->dbName, $table),
                    message: sprintf(
                        'db=%s header=%s DEFERRED by task 8.1: source id is not a carry-verbatim globally-unique '
                        .'VARCHAR (bigint / app-specific target id scheme). Needs a dedicated id strategy — not guessed.',
                        $descriptor->dbName, $table,
                    ),
                );
                $summary['headersDeferred']++;
                $summary['notes']++;
                $say('warn', sprintf('    %s: DEFERRED (id strategy unresolved — see note).', $table));
                continue;
            }

            if (! isset(self::HEADER_ID_COLUMN[$table])) {
                // Not in the carry-verbatim set and not deferred — skip quietly
                // as a note (unknown header shape).
                $this->recordNote(
                    companyId: $tenant->companyId,
                    key: sprintf('unknown_header|%s|%s', $descriptor->dbName, $table),
                    message: sprintf('db=%s header=%s: no column map / id column — skipped.', $descriptor->dbName, $table),
                );
                $summary['notes']++;
                continue;
            }

            if (! $this->targetTableExists($table)) {
                $this->recordNote(
                    companyId: $tenant->companyId,
                    key: sprintf('no_target_table|%s', $table),
                    message: sprintf('db=%s header=%s: no matching target table — skipped.', $descriptor->dbName, $table),
                );
                $summary['notes']++;
                continue;
            }

            try {
                $this->copyHeader($pg, $descriptor, $tenant, $taxonomy, $table, $window, $limit, $say, $summary);
                $summary['headersProcessed']++;
            } catch (Throwable $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('table_failure|%s|%s', $descriptor->dbName, $table),
                    message: sprintf('db=%s header=%s: copy failed: %s', $descriptor->dbName, $table, $e->getMessage()),
                );
                $summary['errors']++;
                $say('error', sprintf('    %s: header FAILED — %s', $table, $e->getMessage()));
            }
        }

        // ---- SEAMS for later tasks ------------------------------------------
        // 8.2: copy bigint-id children by the loaded header set (never by date).
        $this->copyChildrenOf($pg, $descriptor, $tenant, $taxonomy, $presentHeaders, $window, $limit, $say, $summary);
        // 8.3: t_attendance special-case windowed copy + natural-key ledger.
        $this->copyAttendanceWindow($pg, $descriptor, $tenant, $window, $limit, $say, $summary);
    }

    // -------------------------------------------------------------------------
    // 1. Per-DB window computation (R12.1/R12.2/R12.3/R12.9)
    // -------------------------------------------------------------------------

    /**
     * Compute the per-DB transaction window.
     *
     * AnchorDate = MAX(date) across ALL present header tables, each using its own
     * date column resolved against the LIVE source via
     * DateColumnRegistry::validateAgainst (walks the candidate list, so t_vra
     * resolves to vra_created_date etc.). windowStart = AnchorDate − N months.
     *
     * Returned shape:
     *   [
     *     'anchor'      => 'Y-m-d H:i:s' | null,
     *     'windowStart' => 'Y-m-d H:i:s' | null,
     *     'dateColumns' => array<string, ?string>  header => resolved date column
     *                      (null means NO resolvable column → R12.9 full copy).
     *   ]
     *
     * @param  list<string>  $presentHeaders
     * @return array{anchor:?string,windowStart:?string,dateColumns:array<string,?string>}
     */
    public function computeWindow(ConnectionInterface $pg, array $presentHeaders, int $months): array
    {
        $dateColumns = [];
        $anchor = null;

        foreach ($presentHeaders as $table) {
            $col = DateColumnRegistry::validateAgainst($pg, $table);
            $dateColumns[$table] = $col;

            if ($col === null) {
                continue; // R12.9 — no resolvable date column; handled at copy time.
            }

            try {
                $max = $pg->table($table)->max($col);
            } catch (Throwable) {
                $dateColumns[$table] = null; // treat an unqueryable column as unresolved
                continue;
            }

            if ($max === null || $max === '') {
                continue;
            }

            $maxNorm = $this->normalizeDate((string) $max);
            if ($maxNorm === null) {
                continue;
            }

            if ($anchor === null || strcmp($maxNorm, $anchor) > 0) {
                $anchor = $maxNorm;
            }
        }

        $windowStart = null;
        if ($anchor !== null) {
            $windowStart = (new \DateTimeImmutable($anchor))
                ->sub(new \DateInterval('P'.$months.'M'))
                ->format('Y-m-d H:i:s');
        }

        return [
            'anchor'      => $anchor,
            'windowStart' => $windowStart,
            'dateColumns' => $dateColumns,
        ];
    }

    // -------------------------------------------------------------------------
    // 2. VARCHAR-id header copy (R12.3/R12.6/R12.9)
    // -------------------------------------------------------------------------

    /**
     * Copy one VARCHAR-id header: filter by its own date column within the
     * window [windowStart, anchor], carry the VARCHAR id verbatim, inject
     * company_id, remap audit, write schema-drift-safely, and record each loaded
     * header id to the ledger. If the header has no resolvable date column
     * (R12.9) it is FULL-copied with a warning.
     *
     * @param  array{anchor:?string,windowStart:?string,dateColumns:array<string,?string>}  $window
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary
     */
    private function copyHeader(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TableTaxonomy $taxonomy,
        string $table,
        array $window,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        $plan = $taxonomy->classify($table, fn (string $t) => DateColumnRegistry::validateAgainst($pg, $t));
        $targetCols = $this->targetColumns($table);
        $sourceIdCol = self::HEADER_ID_COLUMN[$table];
        $dateCol = $window['dateColumns'][$table] ?? null;

        $fullCopy = ($dateCol === null);
        if ($fullCopy) {
            // R12.9 — no resolvable date column: full copy + WARNING.
            $this->recordNote(
                companyId: $tenant->companyId,
                key: sprintf('no_date_col|%s|%s', $descriptor->dbName, $table),
                message: sprintf(
                    'db=%s header=%s: NO resolvable date column (DateColumnRegistry candidates all absent) — '
                    .'FULL-COPIED (no date filter) per R12.9.',
                    $descriptor->dbName, $table,
                ),
            );
            $summary['headersFullCopied']++;
            $summary['notes']++;
            $say('warn', sprintf('    %s: NO date column — FULL COPY (R12.9).', $table));
        }

        $read = 0;
        $written = 0;
        $existing = 0;
        $errors = 0;

        $this->eachSourceRow(
            $pg, $table, $dateCol, $window['windowStart'], $window['anchor'], $limit, $sourceIdCol,
            function (array $src) use (
                $table, $plan, $tenant, $targetCols, $sourceIdCol, $descriptor,
                &$read, &$written, &$existing, &$errors, &$summary
            ): void {
                $read++;

                // The VARCHAR id is carried verbatim (R12.6). Read it from the
                // SOURCE row BEFORE transform so the idempotency guard is stable.
                $idValue = $src[$sourceIdCol] ?? null;
                if ($this->isBlank($idValue)) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('no_id|%s|%s|%d', $descriptor->dbName, $table, $read),
                        message: sprintf('db=%s header=%s: row has empty id column "%s" — skipped.',
                            $descriptor->dbName, $table, $sourceIdCol),
                    );
                    $errors++;
                    $summary['errors']++;
                    return;
                }
                $idValue = (string) $idValue;

                // Idempotency guard (R14.1/R14.4 — unified/hardened in 8.4). The
                // header, child and attendance guards all follow the SAME shape:
                //   (a) consult the ledger first (cheap, in-process across a
                //       re-run where the live table already holds the row);
                //   (b) fall back to an AUTHORITATIVE live existence check (so a
                //       row inserted by a prior run that predates the ledger is
                //       still never duplicated);
                //   (c) record the ledger marker (idempotent upsert) so the next
                //       run short-circuits at (a) without the live probe.
                // The ledger key is built by the ONE shared header formatter
                // {@see headerLoadedKey()} so a re-run always hits the same key.
                if ($this->headerLoaded($table, $idValue) || $this->headerExists($table, $idValue)) {
                    $existing++;
                    $summary['existing']++;
                    // Keep the loaded-id ledger entry fresh (idempotent upsert)
                    // so 8.2 can select children even for pre-existing headers and
                    // the NEXT run short-circuits on the ledger alone.
                    $this->recordHeaderLoaded($table, $idValue, $tenant->companyId, $descriptor->dbName);
                    // A pre-existing header is still "in the loaded set" — its
                    // children must be (re-)derived from the SAME loaded header set
                    // on a re-run (R14.4), so capture its id even when inserted=0.
                    $this->rememberLoadedHeaderId($descriptor->dbName, $table, $idValue);
                    return;
                }

                try {
                    $mapped = $this->mapColumns($table, $src);
                    $row = $this->transformer->transform($mapped, $plan, $tenant);
                } catch (RowTransformException $e) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('coerce|%s|%s|%s', $descriptor->dbName, $table, $idValue),
                        message: sprintf('db=%s header=%s col=%s id=%s: %s',
                            $descriptor->dbName, $table, $e->column, $idValue, $e->getMessage()),
                    );
                    $errors++;
                    $summary['errors']++;
                    return;
                }

                $values = $this->writeableValues($row, $targetCols);
                // CARRY_VARCHAR_ID: the id MUST be present and verbatim (R12.6).
                $values['id'] = $idValue;

                try {
                    $this->target->table($table)->insert($values);
                } catch (Throwable $e) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('insert|%s|%s|%s', $descriptor->dbName, $table, $idValue),
                        message: sprintf('db=%s header=%s id=%s: insert failed: %s',
                            $descriptor->dbName, $table, $idValue, $e->getMessage()),
                    );
                    $errors++;
                    $summary['errors']++;
                    return;
                }

                $this->recordHeaderLoaded($table, $idValue, $tenant->companyId, $descriptor->dbName);
                $this->rememberLoadedHeaderId($descriptor->dbName, $table, $idValue);
                $written++;
                $summary['inserted']++;
            }
        );

        $this->accumulatePerTable($summary, $table, $descriptor->dbName, $dateCol, $read, $written, $existing, $errors, $fullCopy);
        $say('info', sprintf('    %s [HEADER]: read=%d inserted=%d existing=%d errors=%d (date=%s, company_id=%d)',
            $table, $read, $written, $existing, $errors, $dateCol ?? 'FULL-COPY', $tenant->companyId));
    }

    // -------------------------------------------------------------------------
    // SEAMS for later tasks (deliberate stubs — see class docblock)
    // -------------------------------------------------------------------------

    /**
     * [SEAM — task 8.2] Copy bigint-id children by the loaded header set.
     *
     * Children are selected by the header's VARCHAR business key restricted to
     * the loaded header set (never by date, R12.4), the source bigint id is
     * dropped for a fresh identity (R12.7), the VARCHAR business FK is preserved
     * verbatim, and a child whose header was excluded by the window is NEVER
     * inserted (no orphan, R12.5). The loaded header set comes from the
     * in-memory {@see $loadedHeaderIds} captured during {@see copyHeader()},
     * falling back to the {@see LEDGER_HEADER_LOADED} ledger.
     *
     * IMPLEMENTED in task 8.2. Delegates each in-scope child to
     * {@see copyChildTable()}; see {@see CHILDREN_OF}, {@see CHILD_PARENT_FK},
     * {@see CHILD_NATURAL_KEY}, {@see CHILD_COLUMN_MAP}.
     *
     * @param  list<string>  $presentHeaders
     * @param  array{anchor:?string,windowStart:?string,dateColumns:array<string,?string>}  $window
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary
     */
    private function copyChildrenOf(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TableTaxonomy $taxonomy,
        array $presentHeaders,
        array $window,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        // Children are copied ONLY for parent headers that were actually loaded
        // in the carry-verbatim set this run. A deferred parent (t_overtime,
        // tr_gi_header, tr_gr_header) contributes no loaded-id set, so its
        // children are skipped with a note (R12.5 — no orphans can arise).
        foreach (self::CHILDREN_OF as $parent => $children) {
            foreach ($children as $child) {
                if (! in_array($child, $taxonomy::BIGINT_ID_CHILDREN, true)) {
                    // Defensive: only ever copy known bigint-id children.
                    continue;
                }

                if (! $this->sourceTableExists($pg, $child)) {
                    // Absent in this source DB (e.g. t_oph_detail) — nothing to do.
                    continue;
                }

                if (! $this->targetTableExists($child)) {
                    $this->recordNote(
                        companyId: $tenant->companyId,
                        key: sprintf('no_target_child|%s', $child),
                        message: sprintf('db=%s child=%s: no matching target table — skipped.',
                            $descriptor->dbName, $child),
                    );
                    $summary['notes']++;
                    continue;
                }

                try {
                    $this->copyChildTable(
                        $pg, $descriptor, $tenant, $taxonomy, $parent, $child, $limit, $say, $summary,
                    );
                } catch (Throwable $e) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('child_table_failure|%s|%s', $descriptor->dbName, $child),
                        message: sprintf('db=%s child=%s: copy failed: %s',
                            $descriptor->dbName, $child, $e->getMessage()),
                    );
                    $summary['errors']++;
                    $say('error', sprintf('    %s: child FAILED — %s', $child, $e->getMessage()));
                }
            }
        }

        // Record a note for any in-scope-looking child whose parent header was
        // DEFERRED (so the skip is auditable, R12.5). GI/GR details additionally
        // link by a BIGINT FK and cannot be re-linked by business key (R13.4).
        foreach (self::DEFERRED_HEADERS as $deferred) {
            foreach ($this->deferredChildrenOf($deferred) as $child) {
                if (! $this->sourceTableExists($pg, $child)) {
                    continue;
                }
                $this->recordNote(
                    companyId: $tenant->companyId,
                    key: sprintf('deferred_child|%s|%s', $descriptor->dbName, $child),
                    message: sprintf(
                        'db=%s child=%s SKIPPED: parent header %s is DEFERRED by task 8.1 (no loaded header set); '
                        .'%s. Will be copied once the parent id strategy is resolved.',
                        $descriptor->dbName, $child, $deferred,
                        in_array($child, ['tr_gi_detail', 'tr_gr_detail'], true)
                            ? 'its FK is a BIGINT header id, not a VARCHAR business key'
                            : 'its parent is not in the carry-verbatim set',
                    ),
                );
                $summary['notes']++;
            }
        }
    }

    /**
     * Copy one in-scope child table: select by the parent's VARCHAR business key
     * restricted to the LOADED header set (never by date, R12.4), drop the source
     * bigint id for a fresh identity (R12.7/DROP_REIDENTITY), preserve the VARCHAR
     * business FK verbatim, inject company_id, remap audit, write
     * schema-drift-safely, and dedupe by the child NATURAL KEY so a re-run adds
     * no duplicate (R14.4). A child whose parent header is NOT in the loaded set
     * is never selected (no orphan, R12.5).
     *
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary
     */
    private function copyChildTable(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TableTaxonomy $taxonomy,
        string $parent,
        string $child,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        $fkCol = self::CHILD_PARENT_FK[$child];
        $headerIds = $this->loadedHeaderIdsFor($descriptor->dbName, $parent, $tenant->companyId);

        // No parent headers loaded for this DB → no children in scope (R12.5).
        if ($headerIds === []) {
            $this->accumulatePerChildTable($summary, $child, $descriptor->dbName, $parent, $fkCol, 0, 0, 0, 0);
            $say('info', sprintf('    %s [CHILD of %s]: 0 loaded parent headers — skipped (no orphan).',
                $child, $parent));
            return;
        }

        $plan = $taxonomy->classify($child); // BIGINT_ID_CHILD → DROP_REIDENTITY
        $targetCols = $this->targetColumns($child);
        $fkTargetCol = self::CHILD_COLUMN_MAP[$child][$fkCol] ?? $fkCol;

        $read = 0;
        $inserted = 0;
        $existing = 0;
        $errors = 0;

        /** @var list<array<string,mixed>> $batch */
        $batch = [];

        $batchSeq = 0;
        $flush = function () use (
            &$batch, &$batchSeq, $child, $descriptor, $tenant, &$inserted, &$errors, &$summary
        ): void {
            if ($batch === []) {
                return;
            }
            $batchSeq++;
            $count = count($batch);
            try {
                $this->target->table($child)->insert($batch);
            } catch (Throwable $e) {
                // The batch insert failed as a unit: these rows were counted as
                // inserted optimistically, so roll those counters back and record
                // them as errors instead (R13.5 — record and continue).
                $inserted -= $count;
                $summary['childrenInserted'] -= $count;
                $errors += $count;
                $summary['childrenErrors'] += $count;
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('child_insert|%s|%s|%d', $descriptor->dbName, $child, $batchSeq),
                    message: sprintf('db=%s child=%s: batch #%d (%d rows) insert failed: %s',
                        $descriptor->dbName, $child, $batchSeq, $count, $e->getMessage()),
                );
            }
            $batch = [];
        };

        $this->eachChildRow(
            $pg, $child, $fkCol, $headerIds, $limit,
            function (array $src) use (
                $child, $parent, $plan, $tenant, $targetCols, $fkCol, $fkTargetCol, $descriptor,
                &$read, &$inserted, &$existing, &$errors, &$batch, &$summary, $flush
            ): void {
                $read++;
                $summary['childrenRead']++;

                $fkValue = $src[$fkCol] ?? null;
                if ($this->isBlank($fkValue)) {
                    // A child with an empty parent FK cannot be linked — skip it
                    // (defensive; the IN-filter should exclude these already).
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('child_no_fk|%s|%s|%d', $descriptor->dbName, $child, $read),
                        message: sprintf('db=%s child=%s: empty parent FK "%s" — skipped.',
                            $descriptor->dbName, $child, $fkCol),
                    );
                    $errors++;
                    $summary['childrenErrors']++;
                    return;
                }
                $fkValue = (string) $fkValue;

                try {
                    $mapped = $this->mapColumns($child, $src);
                    $row = $this->transformer->transform($mapped, $plan, $tenant);
                } catch (RowTransformException $e) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('child_coerce|%s|%s|%s|%d', $descriptor->dbName, $child, $fkValue, $read),
                        message: sprintf('db=%s child=%s col=%s fk=%s: %s',
                            $descriptor->dbName, $child, $e->column, $fkValue, $e->getMessage()),
                    );
                    $errors++;
                    $summary['childrenErrors']++;
                    return;
                }

                // writeableValues drops `id` (DROP_REIDENTITY already unset it;
                // SQL Server assigns a fresh identity, R12.7).
                $values = $this->writeableValues($row, $targetCols);

                // Idempotency (R14.4): dedupe by the child NATURAL KEY. Check the
                // ledger first (cheap, in-process across the batch) and the live
                // target (authoritative) so an in-flight batch row is not
                // double-counted and a prior run is a no-op.
                $nkKey = $this->childNaturalKey($child, $tenant->companyId, $fkTargetCol, $fkValue, $values);
                if ($this->childLoaded($nkKey) || $this->childExists($child, $values, $fkTargetCol, $fkValue)) {
                    $existing++;
                    $summary['childrenExisting']++;
                    // Keep the ledger marker fresh (idempotent upsert) so a later
                    // run short-circuits without the live existence probe.
                    $this->recordChildLoaded($nkKey, $tenant->companyId, $descriptor->dbName, $child);
                    return;
                }

                $batch[] = $values;
                $inserted++;
                $summary['childrenInserted']++;
                $this->recordChildLoaded($nkKey, $tenant->companyId, $descriptor->dbName, $child);

                if (count($batch) >= self::INSERT_CHUNK) {
                    $flush();
                }
            }
        );

        $flush();

        $this->accumulatePerChildTable($summary, $child, $descriptor->dbName, $parent, $fkCol, $read, $inserted, $existing, $errors);
        $say('info', sprintf(
            '    %s [CHILD of %s]: read=%d inserted=%d existing=%d errors=%d (fk=%s, parents=%d, company_id=%d)',
            $child, $parent, $read, $inserted, $existing, $errors, $fkCol, count($headerIds), $tenant->companyId,
        ));
    }

    /**
     * Resolve the in-scope child tables of a DEFERRED parent, for auditing the
     * skip. Mirrors the design LLD-4 child registry for the deferred headers.
     *
     * @return list<string>
     */
    private function deferredChildrenOf(string $deferredHeader): array
    {
        return match ($deferredHeader) {
            't_overtime'   => ['t_overtime_detail'],
            'tr_gi_header' => ['tr_gi_detail'],
            'tr_gr_header' => ['tr_gr_detail'],
            default        => [],
        };
    }

    /**
     * [TASK 8.3] t_attendance special-case windowed copy (R12.8).
     *
     * t_attendance is NOT a VARCHAR-id header and NOT a bigint child linked to a
     * header — it is a bigint-id SPECIAL CASE with its OWN date column
     * (attendance_date) and a NATURAL KEY
     * (company_id, employee_code, attendance_date). TableTaxonomy classifies it
     * ATTENDANCE_SPECIAL / PkStrategy DROP_REIDENTITY / injectCompanyId true.
     *
     * Behaviour:
     *   1. WINDOWED BY ITS OWN DATE COLUMN (R12.8): select rows where
     *      attendance_date >= windowStart AND <= anchor, reusing the SAME per-DB
     *      window computed in {@see computeWindow()} (anchor = MAX over the
     *      header tables, windowStart = anchor − N months, R12.1/R12.2). The
     *      window is PER-DB by design (LLD-4 / sequence diagram (d) / LLD-10
     *      copyAttendanceWindow($src, $start)); t_attendance is filtered on its
     *      OWN column but against that SAME window — NO separate attendance
     *      anchor is invented.
     *   2. DROP the source bigint id for a fresh identity; inject company_id
     *      (country_id only where the target column has it, driven by the plan);
     *      remap audit via RowTransformer (DROP_REIDENTITY).
     *   3. RECORD the natural key (company_id, employee_code, attendance_date) →
     *      new target id in the fusion_ledger ({@see LEDGER_ATTENDANCE_NK}) so
     *      later referrers can resolve the new attendance identity (R12.8).
     *   4. SCHEMA-DRIFT-SAFE writes; per-row coercion/insert error → error ledger
     *      and CONTINUE (R13.5). Rows are read in chunks; cross-engine inserts are
     *      per-row (insertGetId) because the new identity MUST be captured for the
     *      ledger — there is no app-generated id to carry.
     *   5. IDEMPOTENCY (R14.4, hardened in 8.4): dedupe by the natural key —
     *      consult the {@see LEDGER_ATTENDANCE_NK} ledger AND the live target
     *      existence by (company_id, employee_code, attendance_date) before an
     *      insert, so a re-run inserts 0 duplicates.
     *
     * ## Window-interpretation decision (documented per the task)
     *   The per-DB window is driven by the HEADER tables' MAX(date). For several
     *   source DBs the header anchor is skewed far into the future (e.g.
     *   t_overtime dates) while attendance_date stops earlier, so the per-DB
     *   window can exclude ALL attendance rows (e.g. sagil_mei: anchor 2026-12,
     *   attendance max 2026-05 → 0 in-window). The design is explicit that the
     *   window is PER-DB (LLD-4, R12.1/R12.2) and R12.8 says "select t_attendance
     *   by its own date window" — meaning filter on its OWN column against that
     *   per-DB window, NOT compute a separate attendance anchor. We follow the
     *   design literally and record a clear NOTE when a DB's attendance predates
     *   the header anchor and the window yields 0 rows, rather than inventing a
     *   per-table anchor (which the design does not authorize).
     *
     * @param  array{anchor:?string,windowStart:?string,dateColumns:array<string,?string>}  $window
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary
     */
    private function copyAttendanceWindow(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        array $window,
        ?int $limit,
        callable $say,
        array &$summary,
    ): void {
        $table = TableTaxonomy::ATTENDANCE;

        // Present in this source DB?
        if (! $this->sourceTableExists($pg, $table)) {
            return; // nothing to copy
        }

        if (! $this->targetTableExists($table)) {
            $this->recordNote(
                companyId: $tenant->companyId,
                key: sprintf('no_target_attendance|%s', $descriptor->dbName),
                message: sprintf('db=%s %s: no matching target table — skipped.', $descriptor->dbName, $table),
            );
            $summary['notes']++;
            return;
        }

        // Resolve the attendance date column against the live source (R12.8).
        // ATTENDANCE_DATE_SRC is the design/registry column; fall back to the
        // DateColumnRegistry resolution to stay schema-honest.
        $dateCol = DateColumnRegistry::validateAgainst($pg, $table) ?? self::ATTENDANCE_DATE_SRC;
        if (! in_array($dateCol, $this->sourceColumns($pg, $table), true)) {
            // The natural-key date column is genuinely absent — never guess.
            $this->recordNote(
                companyId: $tenant->companyId,
                key: sprintf('attendance_no_date_col|%s', $descriptor->dbName),
                message: sprintf(
                    'db=%s %s: attendance date column "%s" not found in source — attendance SKIPPED (not guessed, R12.8).',
                    $descriptor->dbName, $table, $dateCol,
                ),
            );
            $summary['notes']++;
            $say('warn', sprintf('    %s: no attendance_date column — SKIPPED.', $table));
            return;
        }

        // Verify the natural-key employee-code column exists (R12.8 natural key).
        $sourceCols = $this->sourceColumns($pg, $table);
        if (! in_array(self::ATTENDANCE_EMPLOYEE_CODE_SRC, $sourceCols, true)) {
            $this->recordNote(
                companyId: $tenant->companyId,
                key: sprintf('attendance_no_emp_col|%s', $descriptor->dbName),
                message: sprintf(
                    'db=%s %s: natural-key employee column "%s" not found in source — attendance SKIPPED '
                    .'(natural key ambiguous, not guessed, R12.8).',
                    $descriptor->dbName, $table, self::ATTENDANCE_EMPLOYEE_CODE_SRC,
                ),
            );
            $summary['notes']++;
            $say('warn', sprintf('    %s: no employee_code column — SKIPPED.', $table));
            return;
        }

        $windowStart = $window['windowStart'];
        $anchor = $window['anchor'];

        // R12.8 / window-interpretation decision: the per-DB window is driven by
        // the HEADER anchor. If attendance predates it, the window yields 0 rows;
        // record a clear note (we do NOT invent a separate attendance anchor).
        if ($windowStart !== null) {
            $inWindow = $this->attendanceInWindowCount($pg, $table, $dateCol, $windowStart, $anchor);
            if ($inWindow === 0) {
                $attMax = $this->safeMax($pg, $table, $dateCol);
                $this->recordNote(
                    companyId: $tenant->companyId,
                    key: sprintf('attendance_empty_window|%s', $descriptor->dbName),
                    message: sprintf(
                        'db=%s %s: per-DB window [%s .. %s] (from header anchor) excludes ALL attendance rows '
                        .'(attendance max=%s). Following the design per-DB window (LLD-4/R12.1) literally — no separate '
                        .'attendance anchor invented. 0 rows copied.',
                        $descriptor->dbName, $table, $windowStart, $anchor ?? 'NONE', $attMax ?? 'NONE',
                    ),
                );
                $summary['notes']++;
                $say('warn', sprintf(
                    '    %s: per-DB window excludes all attendance (attMax=%s < windowStart=%s) — 0 copied (see note).',
                    $table, $attMax ?? 'NONE', $windowStart,
                ));
            }
        } else {
            // No header anchor at all → no window; attendance is FULL-copied with
            // a warning, consistent with the header R12.9 full-copy behaviour.
            $this->recordNote(
                companyId: $tenant->companyId,
                key: sprintf('attendance_no_window|%s', $descriptor->dbName),
                message: sprintf(
                    'db=%s %s: no per-DB header anchor (no header date resolved) — attendance FULL-COPIED (no date '
                    .'filter), consistent with R12.9.',
                    $descriptor->dbName, $table,
                ),
            );
            $summary['notes']++;
            $say('warn', sprintf('    %s: no window — FULL COPY.', $table));
        }

        $plan = (new TableTaxonomy($this->target))->classify(
            $table,
            fn (string $t) => DateColumnRegistry::validateAgainst($pg, $t),
        ); // ATTENDANCE_SPECIAL → DROP_REIDENTITY, injectCompanyId true
        $targetCols = $this->targetColumns($table);

        $read = 0;
        $inserted = 0;
        $existing = 0;
        $errors = 0;

        $this->eachAttendanceRow(
            $pg, $table, $dateCol, $windowStart, $anchor, $limit,
            function (array $src) use (
                $table, $plan, $tenant, $targetCols, $dateCol, $descriptor,
                &$read, &$inserted, &$existing, &$errors, &$summary
            ): void {
                $read++;
                $summary['attendanceRead']++;

                // Natural-key parts read from the SOURCE row (stable, pre-transform).
                $empCode = $src[self::ATTENDANCE_EMPLOYEE_CODE_SRC] ?? null;
                $attDate = $src[$dateCol] ?? null;

                if ($this->isBlank($empCode) || $this->isBlank($attDate)) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('attendance_no_nk|%s|%d', $descriptor->dbName, $read),
                        message: sprintf(
                            'db=%s %s: row missing natural-key part (employee_code or attendance_date) — skipped.',
                            $descriptor->dbName, $table,
                        ),
                    );
                    $errors++;
                    $summary['attendanceErrors']++;
                    return;
                }

                $empCode = (string) $empCode;
                // Normalize the date to a stable Y-m-d so the ledger key is
                // identical across runs regardless of driver timestamp shape.
                $dateKey = $this->attendanceDateKey($attDate);
                $nkKey = $this->attendanceNaturalKey($tenant->companyId, $empCode, $dateKey);

                // Idempotency (R14.4): ledger first (cheap), then live existence.
                if ($this->attendanceLoaded($nkKey)
                    || $this->attendanceExists($table, $targetCols, $tenant->companyId, $empCode, $dateKey)) {
                    $existing++;
                    $summary['attendanceExisting']++;
                    return;
                }

                try {
                    $mapped = $this->mapColumns($table, $src);
                    $row = $this->transformer->transform($mapped, $plan, $tenant);
                } catch (RowTransformException $e) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('attendance_coerce|%s|%s|%s', $descriptor->dbName, $empCode, $dateKey),
                        message: sprintf('db=%s %s col=%s emp=%s date=%s: %s',
                            $descriptor->dbName, $table, $e->column, $empCode, $dateKey, $e->getMessage()),
                    );
                    $errors++;
                    $summary['attendanceErrors']++;
                    return;
                }

                // writeableValues drops `id` (DROP_REIDENTITY already unset it);
                // SQL Server assigns a fresh identity (R12.8).
                $values = $this->writeableValues($row, $targetCols);

                try {
                    // Per-row insert so the NEW identity can be captured for the
                    // natural-key ledger (R12.8). No app-generated id to carry.
                    $newId = $this->target->table($table)->insertGetId($values, 'id');
                } catch (Throwable $e) {
                    $this->recordError(
                        companyId: $tenant->companyId,
                        key: sprintf('attendance_insert|%s|%s|%s', $descriptor->dbName, $empCode, $dateKey),
                        message: sprintf('db=%s %s emp=%s date=%s: insert failed: %s',
                            $descriptor->dbName, $table, $empCode, $dateKey, $e->getMessage()),
                    );
                    $errors++;
                    $summary['attendanceErrors']++;
                    return;
                }

                $this->recordAttendanceNaturalKey($nkKey, (string) $newId, $tenant->companyId, $descriptor->dbName);
                $inserted++;
                $summary['attendanceInserted']++;
            }
        );

        $say('info', sprintf(
            '    %s [ATTENDANCE]: read=%d inserted=%d existing=%d errors=%d (date=%s, window=[%s..%s], company_id=%d)',
            $table, $read, $inserted, $existing, $errors, $dateCol,
            $windowStart ?? 'FULL', $anchor ?? 'FULL', $tenant->companyId,
        ));
    }

    // -------------------------------------------------------------------------
    // t_attendance helpers (task 8.3).
    // -------------------------------------------------------------------------

    /**
     * Read t_attendance rows filtered to the per-DB window on its OWN date
     * column (R12.8), in deterministic order (by the source bigint id), honouring
     * an optional per-table $limit for guarded smoke runs. When $windowStart is
     * null (no header anchor) the table is full-copied (R12.9 parity).
     *
     * @param  callable(array<string,mixed>):void  $handle
     */
    private function eachAttendanceRow(
        ConnectionInterface $pg,
        string $table,
        string $dateCol,
        ?string $windowStart,
        ?string $anchor,
        ?int $limit,
        callable $handle,
    ): void {
        $orderCol = $this->childSourceIdColumn($pg, $table); // 'attendance_id' bigint
        $remaining = $limit;

        $base = $pg->table($table);
        if ($windowStart !== null) {
            $base->where($dateCol, '>=', $windowStart);
            if ($anchor !== null) {
                $base->where($dateCol, '<=', $anchor);
            }
        }
        if ($orderCol !== null) {
            $base->orderBy($orderCol);
        }

        if ($remaining !== null) {
            foreach ($base->limit($remaining)->get() as $rowObj) {
                $handle((array) $rowObj);
            }
            return;
        }

        $offset = 0;
        do {
            $rows = $base->clone()->offset($offset)->limit(self::READ_CHUNK)->get();
            foreach ($rows as $rowObj) {
                $handle((array) $rowObj);
            }
            $offset += self::READ_CHUNK;
        } while ($rows->count() === self::READ_CHUNK);
    }

    /** Count attendance rows inside the per-DB window (for the empty-window note). */
    private function attendanceInWindowCount(
        ConnectionInterface $pg,
        string $table,
        string $dateCol,
        string $windowStart,
        ?string $anchor,
    ): int {
        $q = $pg->table($table)->where($dateCol, '>=', $windowStart);
        if ($anchor !== null) {
            $q->where($dateCol, '<=', $anchor);
        }

        return (int) $q->count();
    }

    /** MAX(date) on the source, null-safe (for the empty-window note message). */
    private function safeMax(ConnectionInterface $pg, string $table, string $col): ?string
    {
        try {
            $max = $pg->table($table)->max($col);
        } catch (Throwable) {
            return null;
        }

        return ($max === null || $max === '') ? null : (string) $max;
    }

    /**
     * Normalize an attendance_date value to a stable 'Y-m-d' key so the natural
     * key is identical across runs regardless of the driver's timestamp shape.
     * Falls back to the trimmed string when it cannot be parsed (never silently
     * dropped), so a non-standard but non-empty date still yields a stable key.
     */
    private function attendanceDateKey(mixed $date): string
    {
        $raw = trim((string) $date);
        try {
            return (new \DateTimeImmutable($raw))->format('Y-m-d');
        } catch (Throwable) {
            return $raw;
        }
    }

    /**
     * Build the attendance natural-key ledger key:
     *   "company_id|employee_code|attendance_date" (R12.8).
     */
    private function attendanceNaturalKey(int $companyId, string $employeeCode, string $dateKey): string
    {
        return implode('|', [(string) $companyId, $employeeCode, $dateKey]);
    }

    /** Whether an attendance natural key was already recorded in the ledger. */
    private function attendanceLoaded(string $naturalKey): bool
    {
        return $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_ATTENDANCE_NK)
            ->where('key_text', $naturalKey)
            ->exists();
    }

    /**
     * Authoritative live existence check by the attendance natural key
     * (company_id, employee_code, attendance_date). Belt-and-braces alongside the
     * ledger so a row inserted by a prior run (without a ledger entry) is still
     * not duplicated (R14.4).
     *
     * @param  array<int,string>  $targetCols
     */
    private function attendanceExists(
        string $table,
        array $targetCols,
        int $companyId,
        string $employeeCode,
        string $dateKey,
    ): bool {
        $query = $this->target->table($table);
        if (in_array('company_id', $targetCols, true)) {
            $query->where('company_id', $companyId);
        }
        if (in_array('employee_code', $targetCols, true)) {
            $query->where('employee_code', $employeeCode);
        }
        if (in_array('attendance_date', $targetCols, true)) {
            // Compare on the date portion; SQL Server date column holds a date.
            $query->whereDate('attendance_date', $dateKey);
        }

        return $query->exists();
    }

    /**
     * Record a t_attendance natural key → new target id (R12.8). Upserted by
     * (entry_type, key_text) so a re-run adds no ledger row; value_text carries
     * the NEW target id, and the company_id/source_db/table_name columns carry
     * provenance for later referrers.
     */
    private function recordAttendanceNaturalKey(
        string $naturalKey,
        string $newTargetId,
        int $companyId,
        string $sourceDb,
    ): void {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_ATTENDANCE_NK,
                'key_text'   => $naturalKey,
            ],
            [
                'value_text' => $newTargetId,
                'company_id' => $companyId,
                'source_db'  => $sourceDb,
                'table_name' => TableTaxonomy::ATTENDANCE,
                'message'    => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Loaded-header-set tracking (task 8.2 child selection).
    // -------------------------------------------------------------------------

    /** Remember a header id loaded this run (in memory) for child selection. */
    private function rememberLoadedHeaderId(string $db, string $table, string $id): void
    {
        $this->loadedHeaderIds[$db.'|'.$table][] = $id;
    }

    /**
     * The loaded header ids for a (db, parent) — prefers the in-memory set
     * captured during {@see copyHeader()} (efficient), falling back to the
     * {@see LEDGER_HEADER_LOADED} ledger when the parent was not copied this run
     * (e.g. the whole run targeted only children, or a prior partial run).
     * De-duplicated; empty when no parent header is in the loaded set.
     *
     * @return list<string>
     */
    private function loadedHeaderIdsFor(string $db, string $parent, int $companyId): array
    {
        $inMemory = $this->loadedHeaderIds[$db.'|'.$parent] ?? [];
        if ($inMemory !== []) {
            return array_values(array_unique($inMemory));
        }

        // Ledger fallback: ids recorded as "parent|id" for this company + db.
        $prefix = $parent.'|';
        $rows = $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_HEADER_LOADED)
            ->where('table_name', $parent)
            ->where('company_id', $companyId)
            ->where('source_db', $db)
            ->pluck('value_text');

        $ids = [];
        foreach ($rows as $value) {
            if ($value !== null && $value !== '') {
                $ids[] = (string) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    // -------------------------------------------------------------------------
    // Child source reading (by loaded header set, chunked — never by date).
    // -------------------------------------------------------------------------

    /**
     * Read a child table's rows whose parent FK is IN the loaded header-id set
     * (R12.4), in deterministic order, honouring an optional per-table $limit.
     * The IN set is chunked (SQL Server caps parameters near 2100) so a large
     * loaded set does not blow the parameter limit; each chunk is paged by the
     * child's own bigint id for a stable, re-runnable order.
     *
     * @param  list<string>  $headerIds
     * @param  callable(array<string,mixed>):void  $handle
     */
    private function eachChildRow(
        ConnectionInterface $pg,
        string $child,
        string $fkCol,
        array $headerIds,
        ?int $limit,
        callable $handle,
    ): void {
        $childIdCol = $this->childSourceIdColumn($pg, $child);
        $remaining = $limit;

        // Chunk the IN(...) set well under the SQL Server / pg parameter ceiling.
        foreach (array_chunk($headerIds, 1000) as $idChunk) {
            $base = $pg->table($child)->whereIn($fkCol, $idChunk);
            if ($childIdCol !== null) {
                $base->orderBy($childIdCol);
            }

            if ($remaining !== null) {
                foreach ($base->limit($remaining)->get() as $rowObj) {
                    $handle((array) $rowObj);
                    $remaining--;
                    if ($remaining <= 0) {
                        return;
                    }
                }
                continue;
            }

            $offset = 0;
            do {
                $rows = $base->clone()->offset($offset)->limit(self::READ_CHUNK)->get();
                foreach ($rows as $rowObj) {
                    $handle((array) $rowObj);
                }
                $offset += self::READ_CHUNK;
            } while ($rows->count() === self::READ_CHUNK);
        }
    }

    /** The source bigint id column of a child (first `*_id` / `id`), for a stable order. */
    private function childSourceIdColumn(ConnectionInterface $pg, string $child): ?string
    {
        $cols = $this->sourceColumns($pg, $child);
        // Prefer a plain `id`, else the conventional "<singular>_id" bigint PK
        // (e.g. oph_person_id, cp_detail_id) — the first column in each table.
        if (in_array('id', $cols, true)) {
            return 'id';
        }

        return $cols[0] ?? null;
    }

    // -------------------------------------------------------------------------
    // Child idempotency (natural-key dedup — children drop their id, R14.4).
    // -------------------------------------------------------------------------

    /**
     * Build the child natural-key ledger key:
     *   "table|company_id|parentFkValue|disc1=val1|disc2=val2|...".
     * Discriminator columns are the TARGET columns in {@see CHILD_NATURAL_KEY},
     * read from the mapped target row.
     *
     * @param  array<string,mixed>  $values  mapped target row (post-transform)
     */
    private function childNaturalKey(
        string $child,
        int $companyId,
        string $fkTargetCol,
        string $fkValue,
        array $values,
    ): string {
        $parts = [$child, (string) $companyId, $fkTargetCol.'='.$fkValue];
        foreach (self::CHILD_NATURAL_KEY[$child] ?? [] as $col) {
            $v = $values[$col] ?? null;
            $parts[] = $col.'='.($v === null ? '∅' : (string) $v);
        }

        return implode('|', $parts);
    }

    /** Whether a child natural key was already recorded in the ledger. */
    private function childLoaded(string $naturalKey): bool
    {
        return $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_CHILD_LOADED)
            ->where('key_text', $naturalKey)
            ->exists();
    }

    /**
     * Authoritative live existence check by the child natural key: match the
     * company_id, the parent FK, and every discriminator column. Belt-and-braces
     * alongside the ledger so a child inserted by a prior run (without a ledger
     * row) is still not duplicated (R14.4).
     *
     * @param  array<string,mixed>  $values  mapped target row (post-transform)
     */
    private function childExists(string $child, array $values, string $fkTargetCol, string $fkValue): bool
    {
        $targetCols = $this->targetColumns($child);

        $query = $this->target->table($child);
        if (in_array('company_id', $targetCols, true) && array_key_exists('company_id', $values)) {
            $query->where('company_id', $values['company_id']);
        }
        if (in_array($fkTargetCol, $targetCols, true)) {
            $query->where($fkTargetCol, $fkValue);
        }
        foreach (self::CHILD_NATURAL_KEY[$child] ?? [] as $col) {
            if (! in_array($col, $targetCols, true)) {
                continue;
            }
            $v = $values[$col] ?? null;
            if ($v === null) {
                $query->whereNull($col);
            } else {
                $query->where($col, $v);
            }
        }

        return $query->exists();
    }

    /**
     * Record a loaded child natural key (R14.4). Upserted by (entry_type,
     * key_text) so a re-run adds no ledger row. value_text carries the key; the
     * company_id/source_db/table_name columns carry provenance.
     */
    private function recordChildLoaded(string $naturalKey, int $companyId, string $sourceDb, string $child): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_CHILD_LOADED,
                'key_text'   => $naturalKey,
            ],
            [
                'value_text' => '1',
                'company_id' => $companyId,
                'source_db'  => $sourceDb,
                'table_name' => $child,
                'message'    => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Source schema helpers.
    // -------------------------------------------------------------------------

    /** Whether the open source DB has a public table of this name. */
    private function sourceTableExists(ConnectionInterface $pg, string $table): bool
    {
        return $pg->table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->exists();
    }

    /**
     * Ordered source column listing for a table (public schema). Not cached — a
     * child table is read once per DB and the source connection is per-DB.
     *
     * @return list<string>
     */
    private function sourceColumns(ConnectionInterface $pg, string $table): array
    {
        $rows = $pg->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->orderBy('ordinal_position')
            ->pluck('column_name');

        return array_values(array_map('strval', $rows->all()));
    }

    // -------------------------------------------------------------------------
    // Column mapping.
    // -------------------------------------------------------------------------

    /**
     * Map a raw SOURCE header row to TARGET column names.
     *
     * Applies the explicit per-table COLUMN_MAP (renaming CI3-prefixed columns,
     * including the VARCHAR id → `id` and the audit columns to the unprefixed
     * names the RowTransformer understands), then MERGES any same-named source
     * columns that already match a live target column (so the large shared
     * bunches, status and request_id overlaps need no enumeration). Finally, the
     * approved_date/approved_time pair (mapped to the synthetic __approved_date /
     * __approved_time keys) is combined into the target `approved_at` timestamp.
     *
     * @param  array<string,mixed>  $src
     * @return array<string,mixed>
     */
    private function mapColumns(string $table, array $src): array
    {
        // Headers use COLUMN_MAP; bigint-id children (task 8.2) use
        // CHILD_COLUMN_MAP; t_attendance (task 8.3) uses ATTENDANCE_COLUMN_MAP.
        // A table is only ever in one of these.
        if ($table === TableTaxonomy::ATTENDANCE) {
            $map = self::ATTENDANCE_COLUMN_MAP;
        } else {
            $map = self::COLUMN_MAP[$table] ?? self::CHILD_COLUMN_MAP[$table] ?? [];
        }
        $targetCols = $this->targetColumns($table);

        $out = [];

        // 1. explicit renames.
        foreach ($map as $srcCol => $tgtCol) {
            if (array_key_exists($srcCol, $src)) {
                $out[$tgtCol] = $src[$srcCol];
            }
        }

        // 2. same-name passthrough for any source column that matches a live
        //    target column and was not already set by an explicit rename.
        foreach ($src as $col => $value) {
            if (! array_key_exists($col, $out)
                && in_array($col, $targetCols, true)
                && $col !== 'id') { // id is set verbatim by the caller, not here
                $out[$col] = $value;
            }
        }

        // 3. combine approved_date (+time) → approved_at, then drop the synthetics.
        if (array_key_exists('__approved_date', $out)) {
            $approvedAt = $this->combineDateTime(
                $out['__approved_date'] ?? null,
                $out['__approved_time'] ?? null,
            );
            if ($approvedAt !== null && in_array('approved_at', $targetCols, true)) {
                $out['approved_at'] = $approvedAt;
            }
            unset($out['__approved_date'], $out['__approved_time']);
        }

        return $out;
    }

    /**
     * Build the write payload: keep only columns the live target has
     * (schema-drift safe). The identity `id` for a VARCHAR header is set by the
     * caller (carried verbatim) AFTER this, so we drop any `id` left in the
     * mapped row here to avoid a stale value.
     *
     * @param  array<string,mixed>  $row
     * @param  array<int,string>    $targetCols
     * @return array<string,mixed>
     */
    private function writeableValues(array $row, array $targetCols): array
    {
        $values = array_intersect_key($row, array_flip($targetCols));
        unset($values['id']);

        return $values;
    }

    /**
     * Combine a date and optional time into 'Y-m-d H:i:s' (or null). Mirrors the
     * RowTransformer's audit-combine semantics for the approved_at pair.
     */
    private function combineDateTime(mixed $date, mixed $time): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $date = trim((string) $date);
        $time = ($time === null) ? '' : trim((string) $time);

        if ($time !== '') {
            $datePart = explode(' ', $date, 2)[0];
            $candidate = $datePart.' '.$time;
        } else {
            $candidate = str_contains($date, ' ') ? $date : $date.' 00:00:00';
        }

        return $this->normalizeTimestampString($candidate) ?? $candidate;
    }

    // -------------------------------------------------------------------------
    // Source reading (chunked, windowed).
    // -------------------------------------------------------------------------

    /**
     * Read a source header in chunks, filtered to the window when a date column
     * is resolvable (windowStart..anchor inclusive, R12.3), else full copy
     * (R12.9). Honours an optional per-table $limit for guarded smoke runs.
     *
     * @param  callable(array<string,mixed>):void  $handle
     */
    private function eachSourceRow(
        ConnectionInterface $pg,
        string $table,
        ?string $dateCol,
        ?string $windowStart,
        ?string $anchor,
        ?int $limit,
        ?string $orderByCol,
        callable $handle,
    ): void {
        $base = $pg->table($table);

        if ($dateCol !== null && $windowStart !== null) {
            $base->where($dateCol, '>=', $windowStart);
            if ($anchor !== null) {
                $base->where($dateCol, '<=', $anchor);
            }
        }

        // Order by the header's VARCHAR id so BOTH the limited (guarded smoke)
        // read AND the chunked offset read are DETERMINISTIC across runs — a
        // re-run samples the SAME rows, which keeps the idempotency guard
        // provable (without a stable order, --limit could sample different
        // in-window rows each run). The id is unique per header, so it is a
        // total order safe for offset paging too.
        if ($orderByCol !== null) {
            $base->orderBy($orderByCol);
        }

        if ($limit !== null) {
            foreach ($base->limit($limit)->get() as $rowObj) {
                $handle((array) $rowObj);
            }
            return;
        }

        // Chunked by offset over the stable order — adequate for the windowed
        // slice and safe to page because the id gives a total order.
        $offset = 0;
        do {
            $rows = $base->clone()->offset($offset)->limit(self::READ_CHUNK)->get();
            foreach ($rows as $rowObj) {
                $handle((array) $rowObj);
            }
            $offset += self::READ_CHUNK;
        } while ($rows->count() === self::READ_CHUNK);
    }

    /** Which VARCHAR-id design headers physically exist in the source DB. */
    private function presentHeaders(ConnectionInterface $pg): array
    {
        $present = [];
        foreach (TableTaxonomy::VARCHAR_ID_HEADERS as $table) {
            $exists = $pg->table('information_schema.tables')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->exists();
            if ($exists) {
                $present[] = $table;
            }
        }

        return $present;
    }

    // -------------------------------------------------------------------------
    // Ledger helpers (mirror MasterCopier/RbacMigrator upsert-by-(entry_type,key)).
    // -------------------------------------------------------------------------

    /**
     * THE shared loaded-header ledger-key formatter: "table|id" (R14.1/R14.4).
     * Both the write ({@see recordHeaderLoaded()}) and the read
     * ({@see headerLoaded()}) go through this single formatter so a re-run always
     * constructs — and therefore always hits — the identical key. This mirrors
     * the per-entity shared formatters {@see childNaturalKey()} (children) and
     * {@see attendanceNaturalKey()} (attendance): one key formatter per entity.
     */
    private function headerLoadedKey(string $table, string $id): string
    {
        return sprintf('%s|%s', $table, $id);
    }

    /**
     * Whether a VARCHAR header id was already recorded in the ledger
     * (entry_type {@see LEDGER_HEADER_LOADED}). This is the ledger-first leg of
     * the unified header idempotency guard (R14.1/R14.4): consulted BEFORE the
     * authoritative live {@see headerExists()} check so a re-run short-circuits
     * on the ledger alone. Keyed by the ONE shared {@see headerLoadedKey()}
     * formatter so the lookup matches what {@see recordHeaderLoaded()} wrote.
     */
    private function headerLoaded(string $table, string $id): bool
    {
        return $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_HEADER_LOADED)
            ->where('key_text', $this->headerLoadedKey($table, $id))
            ->exists();
    }

    /**
     * Record a loaded VARCHAR header id (R14.1/R14.4). Upserted by (entry_type,
     * key_text) so a re-run adds no ledger row. value_text carries the id; the
     * company_id/source_db/table_name columns carry provenance for 8.2.
     */
    private function recordHeaderLoaded(string $table, string $id, int $companyId, string $sourceDb): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_HEADER_LOADED,
                'key_text'   => $this->headerLoadedKey($table, $id),
            ],
            [
                'value_text' => $id,
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
     * Record an error-ledger entry — upserted by (entry_type, key_text) so a
     * re-run does not pile up duplicate rows (R13.5).
     */
    private function recordError(?int $companyId, string $key, string $message): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            ['entry_type' => self::LEDGER_ERROR, 'key_text' => $key],
            ['company_id' => $companyId, 'message' => $message, 'updated_at' => $now, 'created_at' => $now],
        );
    }

    /** Record a non-fatal note — upserted by (entry_type, key_text). */
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

    /** Whether a VARCHAR header is already present by id (idempotency guard). */
    private function headerExists(string $table, string $id): bool
    {
        return $this->target->table($table)->where('id', $id)->exists();
    }

    /**
     * Live column listing of a target table (schema-drift safe writes). Cached.
     *
     * @return array<int,string>
     */
    private function targetColumns(string $table): array
    {
        return $this->columnCache[$table] ??= $this->target->getSchemaBuilder()->getColumnListing($table);
    }

    /** Whether the live target has a table of this name. */
    private function targetTableExists(string $table): bool
    {
        return $this->target->getSchemaBuilder()->hasTable($table);
    }

    /** A value that is null or an empty/whitespace string. */
    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /** Resolve N (window months): explicit override → config → default 2. */
    private function resolveMonths(?int $override): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return $this->registry->windowMonths();
    }

    /** Normalize a date/timestamp to 'Y-m-d H:i:s', or null when unparseable. */
    private function normalizeDate(string $value): ?string
    {
        return $this->normalizeTimestampString($value);
    }

    private function normalizeTimestampString(string $value): ?string
    {
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Accumulate per-table counters into the summary.
     *
     * @param  array<string,mixed>  $summary
     */
    private function accumulatePerTable(
        array &$summary,
        string $table,
        string $db,
        ?string $dateColumn,
        int $read,
        int $written,
        int $existing,
        int $errors,
        bool $fullCopy,
    ): void {
        $key = $db.'.'.$table;
        $prev = $summary['perTable'][$key] ?? [
            'db' => $db, 'dateColumn' => $dateColumn, 'read' => 0,
            'written' => 0, 'existing' => 0, 'errors' => 0, 'fullCopy' => $fullCopy,
        ];

        $summary['perTable'][$key] = [
            'db'         => $db,
            'dateColumn' => $dateColumn,
            'read'       => $prev['read'] + $read,
            'written'    => $prev['written'] + $written,
            'existing'   => $prev['existing'] + $existing,
            'errors'     => $prev['errors'] + $errors,
            'fullCopy'   => $fullCopy,
        ];
    }

    /**
     * Accumulate per-child-table counters into the summary (task 8.2 breakdown).
     *
     * @param  array<string,mixed>  $summary
     */
    private function accumulatePerChildTable(
        array &$summary,
        string $child,
        string $db,
        string $parent,
        string $parentFk,
        int $read,
        int $inserted,
        int $existing,
        int $errors,
    ): void {
        $key = $db.'.'.$child;
        $prev = $summary['perChildTable'][$key] ?? [
            'db' => $db, 'parent' => $parent, 'parentFk' => $parentFk,
            'read' => 0, 'inserted' => 0, 'existing' => 0, 'errors' => 0,
        ];

        $summary['perChildTable'][$key] = [
            'db'       => $db,
            'parent'   => $parent,
            'parentFk' => $parentFk,
            'read'     => $prev['read'] + $read,
            'inserted' => $prev['inserted'] + $inserted,
            'existing' => $prev['existing'] + $existing,
            'errors'   => $prev['errors'] + $errors,
        ];
    }
}
