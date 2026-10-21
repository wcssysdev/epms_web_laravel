# Implementation Plan: Master HSE (Master SHE) — Admin Role Migration

## Overview

This plan migrates the **Master HSE** module for the **Admin** role from CI3 (`C:\xampp\htdocs\epms24`, branch `CR_HSE_2026`) to Laravel (`c:\laragon\www\epms_laravel`). Work proceeds bottom-up: database migrations → Eloquent models → `HsePortalService` → `BaseHseMasterController` (three-step portal sync) → the 10 concrete portal-synced controllers → Incident Time + HIRARC Template → Email Receiver/Template CRUD → routing, sidebar, and authorization → shared CSV/delete/`is_active` wiring → final verification.

Each task reuses existing conventions: `App\Http\Controllers\Admin\Masters\BaseMasterController` / `BaseGlobalCrudController`, the `app\Database\OdbcSqlSrv` DB layer + SqlSrv DataTables helpers, `App\Models\Master\*` Eloquent models, `App\Services\AuditService`, and the `roles:...` + `company.scope` middleware stack. The DDL authority is `CR_HSE_2026.txt` / `db_update_2026.txt`.

**Out of scope (no tasks created):** HSE transactions (Accident Report, First Aid Kit Usage, HIRARC Draft, Medical Check, Incident Investigation, RCA), approval/closing workflows, training, the mobile API, and the HIRARC apply-to-`t_hirarc` behaviour.

## Tasks

- [x] 1. Create database migrations for HSE master, staging, and config schema
  - [x] 1.1 Create migrations for the five identity-keyed portal-synced master tables
    - Add migrations for `m_category_of_injuries`, `m_type_of_injury`, `m_type_of_disease`, `m_type_of_accident`, `m_first_aid_kit` matching the PostgreSQL DDL in `CR_HSE_2026.txt`
    - Integer `id` primary key with no auto-generated default (EPMS assigns manually), business columns, and full audit columns (`created_by`/`created_date`/`updated_by`/`updated_date`/`deleted_by`/`deleted_date`); `m_first_aid_kit` includes `is_active`, `estate_code`, `division_code`, `uom`; no `company_id` column
    - _Requirements: 1.4, 4.8, 13.3_

  - [x] 1.2 Create migrations for the five natural-key portal-synced master tables
    - Add migrations for `m_process_location` (PK `ploc_id`), `m_likelihood` (PK `lhood_id`, `is_active`), `m_severity` (PK `id` with sequence default), `m_risk_priority_order` (PK `risk_acceptability_name`), `m_course` (PK `course_no_epms`, incl. `course_sync_status`/`course_hse_portal_id`) matching `CR_HSE_2026.txt`
    - No `company_id` column on any table
    - _Requirements: 1.3, 1.4, 13.3_

  - [x] 1.3 Create migration for `m_incident_time` master table
    - PK `incident_time_name` with `incident_time_*_timestamp`/`_by` columns per DDL; no `company_id`
    - _Requirements: 10.1, 10.2, 13.3_

  - [x] 1.4 Create migrations for the `m_master_*` staging mirror tables
    - Add data-only mirror migrations (no audit/PK columns) for the 10 portal-synced masters using the portal-shaped column names from the design Data Models section (e.g. `m_master_severity(severity_code, severity_name, description, severity_rating)`, `m_master_likelihood(likelihood_code, likelihood_name, likelihood_rating, description)`, `m_master_process_location(process_location_code, process_location_name, description)`)
    - _Requirements: 2.2, 4.1_

  - [x] 1.5 Create migration for `zhse_hirarc_template` staging table
    - Data-only staging with `jsonb` `representatives_snapshot`/`details_snapshot` columns and `portal_status` per DDL
    - _Requirements: 11.1_

  - [x] 1.6 Create migrations for `m_hse_email_receiver` and `m_hse_email_template`
    - `m_hse_email_receiver` (PK `id` seq): `receiver_name`, `receiver_email`, `receiver_level`, `receiver_department`, `receiver_notification_type` default `All`, `receiver_is_active` default 1, audit columns
    - `m_hse_email_template` (PK `id` seq, UNIQUE `template_code`): `template_name`, `template_subject`, `template_body`, `template_is_active` default 1, audit columns
    - _Requirements: 7.2, 8.3, 8.4_

  - [x] 1.7 Create migration adding `hse_api_url varchar(500)` to `m_config`
    - Add the nullable `hse_api_url` column to `m_company_config` (the Laravel redesign of CI3-era `m_config`), seeded by `CR_HSE_2026.txt` / `db_update_2026.txt`; mirrors the existing `sap_api_url` pattern on `m_company_config`
    - _Requirements: 12.1, 12.6_

  - [ ]* 1.8 Verify migrations run clean on a test PostgreSQL schema
    - Run `php artisan migrate --pretend` then `php artisan migrate`; assert all 14 masters + staging mirrors + `zhse_hirarc_template` + `m_company_config.hse_api_url` are created
    - _Requirements: 1.4, 13.3_

- [x] 2. Create Eloquent models under `App\Models\Master\Hse`
  - [x] 2.1 Create models for all 14 HSE master + staging tables
    - One model per master table with correct `$table`, `$primaryKey`, `$keyType`, `$incrementing=false` where PK is non-integer or manually assigned, `$timestamps=false` where audit columns are custom-named; models do NOT use `HasCompanyScope` (global/non-tenant)
    - _Requirements: 1.4, 13.3_

  - [ ]* 2.2 Write unit tests for model configuration
    - Assert each model targets the correct table, omits `company_id`, and does not apply a company global scope
    - _Requirements: 1.4, 13.3_

- [x] 3. Implement `HsePortalService` (external HSE Portal HTTP contract)
  - [x] 3.1 Implement `App\Services\HsePortalService` core transport
    - Implement `__construct($baseUrl, $apiKey)`, `fromConfig()` (reads `m_company_config.hse_api_url` via the `CompanyConfig` model (`$config->hse_api_url`), resolving the current company's `CompanyConfig` the same way `SapService` does, + app-config API key), and `get()`/`post()` using `Illuminate\Support\Facades\Http` with `x-api-key` header, `Content-Type: application/json`, 30s timeout, and TLS behaviour from `hse_helper.php`; join base URL + endpoint with exactly one `/`
    - Accept endpoint paths under THREE verified path roots — `MasterData/*` (the 8 portal masters), `training-courses/*` (Course), and `hirarc/*` (HIRARC template) — so the service must NOT assume a `MasterData/` prefix
    - Abort fetch and return a configuration error when `m_company_config.hse_api_url` is unset/empty/whitespace
    - _Requirements: 2.1, 12.1, 12.6_

  - [x] 3.2 Implement `fetchMasterData()` with response-shape normalization and pagination
    - The live HSE Portal returns a **top-level JSON array** (verified); normalize it to a flat ordered item array, and retain `{ "items": [...] }` envelope handling defensively; return an empty array for non-200 or an unrecognized 200 body shape (leaving prior data unchanged)
    - Request successive pages in order and concatenate until a page returns fewer items than `pageSize` or empty, bounded by max 1000 pages
    - _Requirements: 12.2, 12.3, 12.4, 12.5, 12.7_

  - [ ]* 3.3 Write unit tests for `HsePortalService`
    - Use `Http::fake()` to assert: `x-api-key` header and single-`/` URL assembly (**Property 6**, R12.1); array shape (R12.2); `{items}` envelope (R12.3); non-200 → empty (R12.4); malformed 200 → empty (R12.5); missing `hse_api_url` → config error (R12.6); multi-page concatenation and 1000-page bound (R12.7); 30s timeout configured (R2.1)
    - **Validates: Requirements 2.1, 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7**

- [x] 4. Implement `BaseHseMasterController` (abstract three-step portal flow)
  - [x] 4.1 Create `BaseHseMasterController` extending `BaseMasterController`
    - Declare abstract hooks `hseEndpoint()`, `stagingTable()`, `mapPortalItem()`, `stagingToMasterMap()`, `masterKeyColumn()`, `usesManualIdentityKey()`; override `sapConfig()` to return `null` (disable SAP flow) and `baseQuery()` to drop `company_id` scoping (global reads); add `addAuditFields()`
    - _Requirements: 1.4, 13.3_

  - [x] 4.2 Implement `getFromHse()` (STEP 1)
    - Call `HsePortalService::fetchMasterData(hseEndpoint())`; on non-empty items truncate `stagingTable()` and batch-insert `mapPortalItem()` rows, return JSON success with staged count, and write an `AuditService` fetch entry; never mutate the live master
    - Return errors for non-200 (identify HTTP status), empty payload, unreachable/timeout, and unparseable payload — each making no staging change
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 13.4_

  - [x] 4.3 Implement `stagingInfo()` (STEP 2)
    - Return `{current_rows: COUNT(master), new_rows: COUNT(staging)}`, read-only with no mutation of master or staging
    - _Requirements: 3.1, 3.2_

  - [x] 4.4 Implement `replaceFromStaging()` (STEP 3) with transaction, rollback, and concurrency guard
    - Snapshot current master, then inside one DB transaction delete the master and batch-insert staging rows translated via `stagingToMasterMap()` + `addAuditFields()`; on success flash inserted count, write an `AuditService` replace entry with the count, and leave staging intact; on error or count mismatch roll back so the master retains its exact prior contents
    - Return an error (no change) when staging is empty, prompting Get first
    - Reject a concurrent replace for the same master (e.g. atomic lock) with an "already running" error
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 13.5_

  - [x] 4.5 Implement manual identity-key assignment in the replace loop
    - When `usesManualIdentityKey()` is true, compute `next_id = COALESCE(MAX(id),0)+1` after the delete and assign/increment per inserted row so no row has a null `id` and assigned ids are unique within the run
    - _Requirements: 4.8, 4.9_

  - [ ]* 4.6 Write property/behavioural tests for the three-step flow
    - **Property 1: Get is non-destructive to master** — master unchanged after `getFromHse()` (**Validates: Requirements 2.4**)
    - **Property 2: Staging truncate-then-fill** — staging equals the mapped items, no prior-fetch residue (**Validates: Requirements 2.2**)
    - **Property 3: Replace atomicity** — master equals staged set or prior contents, never partial, incl. forced-failure rollback via injected exception (**Validates: Requirements 4.1, 4.5**)
    - **Property 4: Replace requires staging** — empty staging → no change + error (**Validates: Requirements 4.4**)
    - **Property 5: Count fidelity** — `COUNT(master) == COUNT(staging)` at commit (**Validates: Requirements 4.3**)
    - **Property 11: Manual identity key generation** — identity-keyed replace yields non-null, unique, sequential ids from `COALESCE(MAX(id),0)+1` (**Validates: Requirements 4.8, 4.9**)
    - Also assert: concurrent replace rejected (R4.7); staging intact after replace (R4.6); Get success writes audit (R13.4); Replace success writes audit with count (R13.5); reads apply no `company_id` filter — **Property 9** (R13.3)
    - **Validates: Requirements 2.2, 2.4, 4.1, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 13.3, 13.4, 13.5**

- [x] 5. Checkpoint — core portal sync engine
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implement the 10 concrete portal-synced controllers
  - [x] 6.1 Implement `InjuriesCategoryController` and `InjuriesTypeController`
    - Each extends `BaseHseMasterController` declaring model, `tableName()`, `stagingTable()`, `resourceName()`, `viewPrefix()`, `routePrefix()`, `hseEndpoint()`, `datatableColumns()`, `csvHeaders()`, `mapPortalItem()`, `stagingToMasterMap()`, `mapCsvRow()`, `validateRow()`, and `usesManualIdentityKey()=true`
    - Use the VERIFIED camelCase portal field names from the design (e.g. `category-of-injuries` returns `code`/`name`/`acronym`/`injuryType`); `acronym` has no mirror column and is dropped on sync
    - _Requirements: 1.1, 1.2, 2.2, 4.1, 4.8_

  - [x] 6.2 Implement `DiseaseTypeController`, `AccidentTypeController`, and `FirstAidKitController`
    - Same shape; `usesManualIdentityKey()=true`; map the VERIFIED camelCase portal fields per the design's field lists; `FirstAidKitController` surfaces `is_active`, `estate_code`, `division_code`, `uom` columns, but the portal returns no `estate_code`/`division_code` so those stay `null` on sync
    - _Requirements: 1.1, 1.2, 1.3, 2.2, 4.1, 4.8_

  - [x] 6.3 Implement `ProcessLocationController`, `LikelihoodController`, and `SeverityController`
    - Natural/sequence-keyed (`usesManualIdentityKey()=false`); `stagingToMasterMap()` resolves portal→master column renames (e.g. `m_master_severity`→`m_severity`, `m_master_likelihood`→`m_likelihood` `lhood_*`, `m_master_process_location`→`m_process_location` `ploc_*`) using the VERIFIED camelCase portal fields from the design; `LikelihoodController` surfaces `is_active`
    - _Requirements: 1.1, 1.2, 1.3, 2.2, 4.1_

  - [x] 6.4 Implement `RiskPriorityOrderController` and `CourseController`
    - `RiskPriorityOrderController` keyed by `risk_acceptability_name`; `hseEndpoint()` = `MasterData/risk-acceptabilities` (NOT `risk-priority-orders`, which 404s). VERIFIED portal fields: `id, minValue, maxValue, riskLevel, riskAcceptability, actionDescription, isActive`
    - **Implementation decision (reconciliation):** map `riskAcceptability→risk_acceptability_name`, `minValue→risk_range_lower`, `maxValue→risk_range_upper`; decide the `risk_order` source (`riskLevel` vs `id`); `actionDescription` has no mirror column and is dropped
    - `CourseController` keyed by `course_no_epms`; `hseEndpoint()` = `training-courses/all` (NOT `MasterData/courses`, which does not exist) — a non-`MasterData/` path root; map sync columns per the design's Course verification note
    - _Requirements: 1.1, 1.2, 2.2, 4.1_

  - [x] 6.5 Create Blade views + DataTables for the 10 portal-synced masters
    - Index view per master wiring the server-side DataTable (via the existing `SqlSrv`/Yajra helpers), Get / Refresh Info / Replace buttons to the three-step endpoints, CSV upload + template download, and delete-with-confirmation; display `is_active` where present
    - _Requirements: 1.1, 1.2, 1.3, 5.1, 5.2, 6.1, 6.2_

  - [ ]* 6.6 Write unit tests for per-controller mapping
    - Assert `mapPortalItem()` and `stagingToMasterMap()` for each controller, focusing on the renamed Severity/Likelihood/Process Location columns
    - _Requirements: 2.2, 4.1_

- [x] 7. Implement Incident Time and HIRARC Template controllers
  - [x] 7.1 Implement `IncidentTimeController` (CRUD + CSV)
    - CONFIRMED CRUD + CSV: the live HSE Portal exposes no incident-time endpoint (verified against Swagger), so there is no portal sync for this master
    - Extend `BaseGlobalCrudController` with the CSV mixin; DataTable listing, require non-empty `incident_time_name`, CSV upload + template download + delete-with-confirmation; add index Blade view + route wiring
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 7.2 Implement `HirarcTemplateController` (fetch-to-staging only)
    - Implement `index()` (staging vs current compare view), `getTemplate()` (portal fetch via `training-courses`-style non-`MasterData/` path `hirarc/all` — i.e. `/api/hirarc/all`, or `/published` — → truncate+insert `zhse_hirarc_template`), and `refreshInfo()` (count compare); the apply-to-`t_hirarc` action is intentionally omitted/disabled with a note pointing to the deferred HIRARC transaction spec (out of scope)
    - _Requirements: 11.1, 11.2, 11.3, 13.6_

  - [ ]* 7.3 Write tests for Incident Time and HIRARC Template
    - Incident Time: required `incident_time_name`, CSV template download, delete confirmation (R10.2, R10.3); HIRARC: fetch truncates+inserts staging, index compares counts, and no write to `t_hirarc` occurs (R11.1, R11.2, R11.3)
    - _Requirements: 10.2, 10.3, 11.1, 11.2, 11.3_

- [x] 8. Implement Email Receiver and Email Template full CRUD
  - [x] 8.1 Implement `EmailReceiverController`
    - Extend `BaseGlobalCrudController`; DataTable listing with `receiver_is_active` state; require `receiver_name`, valid `receiver_email`, `receiver_level`, `receiver_department`; reject duplicate `(receiver_email, receiver_notification_type)` before write; create/edit views with Select2 dropdowns backed by preserved static option sets
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [x] 8.2 Implement `EmailTemplateController` CRUD with CKEditor body
    - Extend `BaseGlobalCrudController`; DataTable listing with `template_is_active`; require `template_code` (unique, rejected before write), `template_name`, `template_subject`, `template_body`; create/edit views provide a CKEditor HTML editor for `template_body`
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [x] 8.3 Implement `EmailTemplateController::preview()` with placeholder substitution
    - Add `preview($id)` and `placeholderSamples()`; render subject/body with every known `Placeholder_Token` replaced by a sample value, leaving the stored row unchanged; return a not-found error for a missing template; add preview route + view
    - _Requirements: 9.1, 9.2, 9.3_

  - [ ]* 8.4 Write tests for Email CRUD, uniqueness, and preview purity
    - **Property 7: CRUD uniqueness** — duplicate `template_code` and duplicate `(receiver_email, receiver_notification_type)` rejected before write (**Validates: Requirements 7.3, 8.4**)
    - **Property 8: Preview purity** — `preview()` does not mutate `m_hse_email_template` (**Validates: Requirements 9.2**)
    - Also assert required-field validation (R7.2, R8.3), preview substitutes known tokens (R9.1), missing-template preview error (R9.3), and `is_active` shown in listings (R7.5, R8.5)
    - **Validates: Requirements 7.2, 7.3, 8.3, 8.4, 9.1, 9.2, 9.3**

- [x] 9. Wire routing, sidebar, and authorization
  - [x] 9.1 Register the `masters.hse.*` route group
    - Add the `hse.` segment under the existing `masters.*` group with the `$hseMasterRoutes` macro (index/datatable/upload/preview/save/cancel + `get-from-hse`/`staging-info`/`replace-from-staging` + export/generate-csv) for the 10 portal masters, plus Incident Time (CRUD+CSV), HIRARC Template (index/get-template/refresh-info), Email Receiver (CRUD), and Email Template (CRUD + `/{id}/preview`)
    - _Requirements: 6.2_

  - [x] 9.2 Apply authorization middleware to the `masters.hse.*` group
    - Gate all `masters.hse.*` routes with `roles:super_admin,country_admin,company_admin,admin,it_staff` + `auth.check`; HSE masters read without `company.scope`'s `company_id` filter (global)
    - _Requirements: 13.1, 13.2, 13.3_

  - [x] 9.3 Add Master SHE sidebar/menu entries for the Admin role
    - Add the Master SHE menu group listing the 14 sub-modules, visible only to authorized Admin roles
    - _Requirements: 13.1_

  - [ ]* 9.4 Write authorization feature tests
    - **Property 10: Authorization** — authorized Admin roles reach `masters.hse.*`; a non-authorized role receives HTTP 403 (**Validates: Requirements 13.1, 13.2**)
    - Also assert `route:list --name=masters.hse` registers index/sync/CSV routes for each master
    - **Validates: Requirements 13.1, 13.2**

- [x] 10. Wire shared CSV, delete-with-confirmation, and `is_active` behaviour
  - [x] 10.1 Confirm CSV import/template and dedupe across masters
    - Ensure CSV template download emits defined headers, upload previews parsed rows before persisting, invalid MIME/extension is rejected, row-validation failures are flagged and excluded, non-empty `Natural_Key` is required, and rows are deduplicated so each `Natural_Key` is globally unique before insert
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [x] 10.2 Confirm delete-with-confirmation and `is_active` display wiring
    - Ensure deletes require explicit confirmation and run via a CSRF-protected state-changing request; ensure masters with an `is_active` column display active state in their listing
    - _Requirements: 1.3, 6.1, 6.2, 7.5, 8.5_

  - [ ]* 10.3 Write integration tests for shared CSV/delete behaviour
    - Assert template headers (R5.1), upload preview (R5.2), MIME rejection (R5.3), invalid-row exclusion (R5.4), Natural_Key dedupe (R5.5), confirm-and-persist (R5.6), delete requires confirmation + CSRF (R6.1, R6.2)
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.1, 6.2_

- [x] 11. Final verification
  - Run `php artisan migrate` on the test schema, `php artisan route:list --name=masters.hse`, the full test suite (`php artisan test --filter=Hse`), and the project's linter/static-analysis; fix any failures so the migrated Master HSE (Admin) module passes end to end
  - _Requirements: 1.4, 13.1, 13.2, 13.6_

## Notes

- Tasks marked with `*` are optional (tests) and can be skipped for a faster MVP, but they validate the correctness properties in the design.
- Each task references the specific requirement sub-clauses it satisfies for traceability.
- Property tests use Pest + `Http::fake()` and injected-exception rollback checks, per the design Testing Strategy.
- Scope is strictly Admin-role Master HSE. HSE transactions, approval/closing, training, mobile API, and HIRARC apply-to-`t_hirarc` are excluded (Requirement 13.6) and have no tasks.

### Portal verification — resolved vs open

Resolved (VERIFIED against the live HSE Portal + Swagger, recorded in design.md):
- ✅ Portal endpoint paths and camelCase JSON field names confirmed for all portal-synced masters; responses are top-level JSON arrays with `?pageNumber`/`?pageSize` pagination.
- ✅ Endpoints span three path roots — `MasterData/*`, `training-courses/*`, `hirarc/*` (reflected in Tasks 3.1, 6.4, 7.2).
- ✅ Risk Priority Order endpoint is `MasterData/risk-acceptabilities` (not `risk-priority-orders`, which 404s).
- ✅ Course uses `training-courses/all` (no `MasterData/courses` exists).
- ✅ HIRARC template fetch uses `/api/hirarc/all` (or `/published`), not a `MasterData/*` path.
- ✅ `m_incident_time` has no portal endpoint → CRUD + CSV (Task 7.1).

Remaining implementation decisions (not blockers):
- Risk Priority Order `risk_order` source: `riskLevel` vs `id` (Task 6.4).
- Category of Injuries `acronym` is dropped — no mirror column (Task 6.1).
- First Aid Kit `estate_code`/`division_code` stay `null` — portal returns neither (Task 6.2).
- Course sync-column mapping (`course_sync_status`/`course_hse_portal_id`) per the design's Course verification note (Task 6.4).

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.4", "1.5", "1.6", "1.7"] },
    { "id": 1, "tasks": ["1.8", "2.1", "3.1"] },
    { "id": 2, "tasks": ["2.2", "3.2"] },
    { "id": 3, "tasks": ["3.3", "4.1"] },
    { "id": 4, "tasks": ["4.2", "4.3", "4.4"] },
    { "id": 5, "tasks": ["4.5"] },
    { "id": 6, "tasks": ["4.6", "6.1", "6.2", "6.3", "6.4"] },
    { "id": 7, "tasks": ["6.5", "6.6", "7.1", "7.2", "8.1", "8.2"] },
    { "id": 8, "tasks": ["7.3", "8.3"] },
    { "id": 9, "tasks": ["8.4", "9.1"] },
    { "id": 10, "tasks": ["9.2", "9.3", "10.1", "10.2"] },
    { "id": 11, "tasks": ["9.4", "10.3"] }
  ]
}
```
