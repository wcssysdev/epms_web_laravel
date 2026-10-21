# Requirements Document

## Introduction

This feature migrates the **Master HSE** module (labelled "Master SHE" in the UI) for the **Admin** role from the legacy CodeIgniter 3 application (`C:\xampp\htdocs\epms24`, branch `CR_HSE_2026`) to the Laravel application (`c:\laragon\www\epms_laravel`). The scope covers 14 HSE master sub-modules across three behavioural families:

- **10 portal-synced read-only masters** populated from an external HSE Portal REST API through a three-step sync flow (*Get All Data From HSE* → *Refresh Master Data Info* → *Replace Master Data*) buffered through staging mirror tables: Category of Injuries, Type of Injury, Type of Disease, Type of Accident, First Aid Kit, Process Location, Likelihood, Severity, Risk Priority Order, and Course.
- **2 full-CRUD masters** authored entirely within EPMS: Email Receiver and Email Template.
- **2 additional masters**: Incident Time (CRUD + CSV) and HIRARC Template (fetch-to-staging only).

All HSE master tables are global (non-tenant, no `company_id`). The feature preserves the external HSE Portal HTTP contract, reuses existing Laravel master-controller scaffolding, applies audit logging on sync operations, and restricts access to the Admin role.

**Out of scope:** HSE transactions (Accident Report, First Aid Kit Usage, HIRARC Draft, Medical Check, Incident Investigation, RCA), approval/closing workflows, training, the mobile API, and the HIRARC "apply template to `t_hirarc`" transaction behaviour. These are explicitly excluded and documented in Requirement 13.

## Glossary

- **HSE_Master_System**: The Laravel subsystem managing Master HSE (Master SHE) master data for the Admin role.
- **Portal_Synced_Master**: A read-only HSE master whose live data is replaced from the external HSE Portal via the three-step sync flow (the 10 masters listed above).
- **Staging_Table**: A data-only mirror table (`m_master_*` or `zhse_hirarc_template`) that buffers fetched portal data before it replaces the live master.
- **Live_Master**: The authoritative HSE master table (`m_*`) read by the application UI.
- **HSE_Portal_Service**: The Laravel service (`App\Services\HsePortalService`) that encapsulates the external HSE Portal HTTP contract, replacing the CI3 `hse_helper.php` functions.
- **HSE_Portal**: The external HSE Portal REST API exposing `MasterData/*` endpoints.
- **Get_Operation**: STEP 1 of the sync flow — fetch all data from the HSE Portal into the Staging_Table.
- **Refresh_Info_Operation**: STEP 2 of the sync flow — compare current Live_Master row count against Staging_Table row count.
- **Replace_Operation**: STEP 3 of the sync flow — replace Live_Master contents with Staging_Table contents inside a transaction.
- **DataTable**: The server-side Yajra DataTables listing with search, sort, and pagination.
- **Audit_Service**: The existing `App\Services\AuditService` that records master data operations.
- **Admin_User**: A user holding an authorized role (`super_admin`, `country_admin`, `company_admin`, `admin`, or `it_staff`), corresponding to CI3 `user_role` 1.
- **Email_Template**: A full-CRUD master (`m_hse_email_template`) with a unique `template_code` and an HTML body authored via CKEditor.
- **Email_Receiver**: A full-CRUD master (`m_hse_email_receiver`) listing notification recipients.
- **Placeholder_Token**: A token such as `{estate_code}`, `{date}`, or `{employee_code}` substituted with a sample value during Email_Template preview.
- **HIRARC_Template_Master**: The HSE master that fetches HIRARC templates into the `zhse_hirarc_template` Staging_Table (fetch-only; apply-to-transaction out of scope).
- **Natural_Key**: The business key column of a master (e.g. `category_injuries_code`) used for CSV validation and deduplication.
- **HSE_Portal_Api_Key**: The application-configured API key sent in the `x-api-key` header to the HSE_Portal (sourced from application/environment config, not a database column).
- **Identity_Primary_Key**: An integer primary key column (e.g. `id`) of a Live_Master for which the HSE_Portal source does not supply a database-generated default, requiring EPMS to compute the key manually as `COALESCE(MAX(existing id), 0) + 1` incrementing per inserted row (applies to `m_category_of_injuries`, `m_type_of_injury`, `m_type_of_disease`, `m_type_of_accident`, and `m_first_aid_kit`).

## Requirements

### Requirement 1: Portal-synced master listing and display

**User Story:** As an Admin_User, I want to view each portal-synced HSE master in a searchable, sortable, paginated list, so that I can inspect the current master data.

#### Acceptance Criteria

1. WHEN an Admin_User opens a Portal_Synced_Master index page, THE HSE_Master_System SHALL render a DataTable listing the Live_Master rows for that master.
2. THE DataTable SHALL provide server-side search, sort, and pagination for the listed rows.
3. WHERE a Portal_Synced_Master table has an `is_active` column, THE HSE_Master_System SHALL display the active state of each row.
4. WHEN a Portal_Synced_Master index page is requested, THE HSE_Master_System SHALL return rows regardless of `company_id`.

### Requirement 2: Get All Data From HSE (STEP 1)

**User Story:** As an Admin_User, I want to fetch all data for a master from the HSE Portal into a staging area, so that I can review it before replacing the live master.

#### Acceptance Criteria

1. WHEN an Admin_User triggers the Get_Operation for a Portal_Synced_Master, THE HSE_Master_System SHALL request the master data from the HSE_Portal through the HSE_Portal_Service using a request timeout of 30 seconds.
2. WHEN the HSE_Portal returns HTTP 200 with a non-empty payload, THE HSE_Master_System SHALL truncate the Staging_Table and insert the mapped portal items into the Staging_Table.
3. WHEN the Get_Operation completes successfully, THE HSE_Master_System SHALL return a success response containing the staged row count.
4. WHEN the Get_Operation completes, THE HSE_Master_System SHALL leave the Live_Master contents unchanged.
5. IF the HSE_Portal returns a received HTTP response with a status other than 200, THEN THE HSE_Master_System SHALL return an error identifying the HTTP status and make no change to the Staging_Table.
6. IF the HSE_Portal returns HTTP 200 with an empty payload, THEN THE HSE_Master_System SHALL return an error indicating the master data is unavailable and make no change to the Staging_Table.
7. IF no HTTP response is received from the HSE_Portal within the 30-second request timeout or the connection fails, THEN THE HSE_Master_System SHALL return an error indicating the HSE_Portal is unreachable and make no change to the Staging_Table.
8. IF the HSE_Portal returns HTTP 200 with a payload that cannot be parsed or mapped into Staging_Table items, THEN THE HSE_Master_System SHALL return an error indicating the payload is invalid and make no change to the Staging_Table.

### Requirement 3: Refresh Master Data Info (STEP 2)

**User Story:** As an Admin_User, I want to compare the current master row count against the staged row count, so that I understand the effect of a replace before running it.

#### Acceptance Criteria

1. WHEN an Admin_User triggers the Refresh_Info_Operation for a Portal_Synced_Master, THE HSE_Master_System SHALL return the current Live_Master row count and the Staging_Table row count.
2. WHEN the Refresh_Info_Operation runs, THE HSE_Master_System SHALL leave both the Live_Master and the Staging_Table unchanged.

### Requirement 4: Replace Master Data (STEP 3) with transactional rollback

**User Story:** As an Admin_User, I want to replace the live master with the staged data in a single atomic operation, so that the master is never left in a partial state.

#### Acceptance Criteria

1. WHEN an Admin_User triggers the Replace_Operation and the Staging_Table is non-empty, THE HSE_Master_System SHALL replace the Live_Master contents with the Staging_Table rows translated through the staging-to-master column mapping with audit fields applied, inside one database transaction.
2. WHEN the Replace_Operation commits successfully, THE HSE_Master_System SHALL return a success response reporting the inserted row count.
3. WHEN the Replace_Operation commits successfully, THE Live_Master row count SHALL equal the Staging_Table row count.
4. IF the Staging_Table is empty, THEN THE HSE_Master_System SHALL make no change to the Live_Master and return an error prompting the Admin_User to run the Get_Operation first.
5. IF the Replace_Operation encounters an error or a row-count mismatch, THEN THE HSE_Master_System SHALL roll back the transaction so the Live_Master retains the exact contents it held immediately before the Replace_Operation began, and return an error indicating the rollback.
6. WHEN the Replace_Operation completes, THE HSE_Master_System SHALL leave the Staging_Table intact to allow idempotent re-runs.
7. IF a Replace_Operation is already in progress for the same Portal_Synced_Master, THEN THE HSE_Master_System SHALL reject a concurrent Replace_Operation for that master and return an error indicating a replace is already running.
8. WHERE a Live_Master uses an integer identity primary key, WHEN the Replace_Operation inserts staging rows, THE HSE_Master_System SHALL assign each inserted row a primary key beginning at COALESCE(MAX(existing id), 0) + 1 and incrementing by one per row, so that no row is inserted with a null primary key.
9. WHEN the Replace_Operation assigns generated primary keys during a single run, THE HSE_Master_System SHALL ensure the assigned primary keys are unique within the Live_Master.

### Requirement 5: CSV import and template download for masters

**User Story:** As an Admin_User, I want to upload master rows from a CSV file and download a CSV template, so that I can bulk-edit master data offline.

#### Acceptance Criteria

1. WHEN an Admin_User requests a CSV template for a master, THE HSE_Master_System SHALL generate a downloadable CSV file containing the master's defined column headers.
2. WHEN an Admin_User uploads a CSV file, THE HSE_Master_System SHALL present a preview of the parsed rows before persisting them.
3. IF an uploaded file has an invalid MIME type or extension, THEN THE HSE_Master_System SHALL reject the upload with a validation error.
4. IF a CSV row fails its row validation, THEN THE HSE_Master_System SHALL flag the row in the preview and exclude it from the saved data.
5. WHEN CSV rows are prepared for insertion, THE HSE_Master_System SHALL require a non-empty Natural_Key for each row and SHALL deduplicate rows so that each Natural_Key value is globally unique within the master before insert.
6. WHEN an Admin_User confirms a previewed CSV upload, THE HSE_Master_System SHALL persist the validated rows.

### Requirement 6: Delete with confirmation

**User Story:** As an Admin_User, I want to delete a master row only after confirming the action, so that I avoid accidental data loss.

#### Acceptance Criteria

1. WHEN an Admin_User requests deletion of a master row, THE HSE_Master_System SHALL require an explicit confirmation before performing the deletion.
2. WHEN an Admin_User confirms a deletion, THE HSE_Master_System SHALL delete the row using a state-changing request protected by CSRF.

### Requirement 7: Email Receiver full CRUD

**User Story:** As an Admin_User, I want to create, read, update, and delete email notification receivers, so that HSE notifications reach the correct recipients.

#### Acceptance Criteria

1. WHEN an Admin_User opens the Email_Receiver index, THE HSE_Master_System SHALL render a DataTable of Email_Receiver rows with search, sort, and pagination.
2. WHEN an Admin_User submits a new Email_Receiver, THE HSE_Master_System SHALL require `receiver_name`, a valid `receiver_email`, `receiver_level`, and `receiver_department`.
3. IF a submitted Email_Receiver duplicates an existing `(receiver_email, receiver_notification_type)` pair, THEN THE HSE_Master_System SHALL reject the submission before writing and report that the receiver already exists.
4. WHEN an Admin_User edits the level, department, or notification type of an Email_Receiver, THE HSE_Master_System SHALL present the choices through Select2 dropdowns backed by the preserved static option sets.
5. WHERE an Email_Receiver has a `receiver_is_active` value, THE HSE_Master_System SHALL display the active state in the listing.

### Requirement 8: Email Template full CRUD

**User Story:** As an Admin_User, I want to manage email templates with an HTML body and a unique code, so that HSE notifications use consistent, editable content.

#### Acceptance Criteria

1. WHEN an Admin_User opens the Email_Template index, THE HSE_Master_System SHALL render a DataTable of Email_Template rows with search, sort, and pagination.
2. WHEN an Admin_User creates or edits an Email_Template, THE HSE_Master_System SHALL provide a CKEditor HTML editor for the `template_body` field.
3. WHEN an Admin_User submits a new Email_Template, THE HSE_Master_System SHALL require `template_code`, `template_name`, `template_subject`, and `template_body`.
4. IF a submitted Email_Template duplicates an existing `template_code`, THEN THE HSE_Master_System SHALL reject the submission before writing and report that the code already exists.
5. WHERE an Email_Template has a `template_is_active` value, THE HSE_Master_System SHALL display the active state in the listing.

### Requirement 9: Email Template preview

**User Story:** As an Admin_User, I want to preview an email template with placeholder tokens filled in, so that I can verify the rendered subject and body.

#### Acceptance Criteria

1. WHEN an Admin_User requests a preview of an existing Email_Template, THE HSE_Master_System SHALL render the template subject and body with every known Placeholder_Token replaced by a sample value.
2. WHEN an Email_Template preview is rendered, THE HSE_Master_System SHALL leave the stored Email_Template row unchanged.
3. IF a preview is requested for a non-existent Email_Template, THEN THE HSE_Master_System SHALL return an error indicating the template was not found.

### Requirement 10: Incident Time master

**User Story:** As an Admin_User, I want to manage the Incident Time lookup, so that incident reporting can reference defined time bands.

#### Acceptance Criteria

1. WHEN an Admin_User opens the Incident Time master, THE HSE_Master_System SHALL render a DataTable of Incident Time rows with search, sort, and pagination.
2. WHEN an Admin_User submits an Incident Time entry, THE HSE_Master_System SHALL require a non-empty `incident_time_name`.
3. WHEN an Admin_User manages Incident Time rows, THE HSE_Master_System SHALL support CSV upload, CSV template download, and delete-with-confirmation consistent with the other masters.

### Requirement 11: HIRARC Template fetch-to-staging

**User Story:** As an Admin_User, I want to fetch HIRARC templates from the portal into a staging area and compare counts, so that templates are available for a future apply step.

#### Acceptance Criteria

1. WHEN an Admin_User triggers the HIRARC_Template_Master fetch, THE HSE_Master_System SHALL request HIRARC templates from the HSE_Portal and truncate-then-insert them into the `zhse_hirarc_template` Staging_Table.
2. WHEN an Admin_User opens the HIRARC_Template_Master index, THE HSE_Master_System SHALL display a comparison of the staged template count against the current count.
3. THE HSE_Master_System SHALL NOT apply staged HIRARC templates into `t_hirarc` (the apply-to-transaction behaviour is out of scope per Requirement 13).

### Requirement 12: HSE Portal external contract preservation

**User Story:** As a developer migrating from CI3, I want the HSE Portal HTTP contract preserved exactly, so that the external portal integration keeps working without portal-side changes.

#### Acceptance Criteria

1. WHEN the HSE_Portal_Service issues a portal request, THE HSE_Portal_Service SHALL include the `x-api-key` header populated with the application-configured HSE Portal API key and target the request URL formed by joining the base URL read from `m_company_config.hse_api_url` with the endpoint path using exactly one `/` separator.
2. WHEN the HSE_Portal_Service parses a response with status 200 whose body is a top-level JSON array, THE HSE_Portal_Service SHALL return that array as a flat item array preserving element order.
3. WHEN the HSE_Portal_Service parses a response with status 200 whose body is a `{ "items": [...] }` envelope, THE HSE_Portal_Service SHALL return the value of the `items` field as a flat item array preserving element order.
4. IF the HSE_Portal returns a status other than 200, THEN THE HSE_Portal_Service SHALL return an empty item array so the caller treats the result as a fetch error, while leaving any previously fetched data unchanged.
5. IF a status 200 response body is neither a top-level JSON array nor a `{ "items": [...] }` envelope, THEN THE HSE_Portal_Service SHALL return an empty item array so the caller treats the result as a fetch error.
6. IF `m_company_config.hse_api_url` is unset, empty, or contains only whitespace, THEN THE HSE_Master_System SHALL abort the fetch without issuing a portal request and return an error message identifying the missing `m_company_config.hse_api_url` configuration.
7. WHERE a portal resource returns more items than fit in one page, THE HSE_Portal_Service SHALL request successive pages in order and concatenate their item arrays until a page returns fewer items than the page size or an empty item array, bounded by a maximum of 1000 pages per fetch.

### Requirement 13: Authorization, global scope, audit, and scope boundary

**User Story:** As a system owner, I want Master HSE restricted to Admin users, scoped globally, audited on sync, and bounded to master data only, so that access and data integrity match the CI3 intent.

#### Acceptance Criteria

1. WHILE a request targets a `masters.hse.*` route, THE HSE_Master_System SHALL require the requester to hold an Admin_User role (`super_admin`, `country_admin`, `company_admin`, `admin`, or `it_staff`).
2. IF a non-authorized role requests a `masters.hse.*` route, THEN THE HSE_Master_System SHALL deny access with an HTTP 403 response.
3. WHEN the HSE_Master_System reads any HSE master, THE HSE_Master_System SHALL apply no `company_id` filter because the HSE master tables are non-tenant.
4. WHEN a Get_Operation completes successfully, THE HSE_Master_System SHALL write an Audit_Service entry recording the fetch.
5. WHEN a Replace_Operation commits successfully, THE HSE_Master_System SHALL write an Audit_Service entry recording the replace with the inserted row count.
6. THE HSE_Master_System SHALL exclude HSE transactions, approval/closing workflows, training, the mobile API, and the HIRARC apply-to-transaction behaviour from this feature.
