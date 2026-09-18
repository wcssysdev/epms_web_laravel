# Core Development Guideline: CI3 as Ground Truth for Laravel Migration

## Fundamental Rule
This project is a **MIGRATION from CodeIgniter 3 (CI3) to Laravel (+ Centralize architecture)**.
For **EVERY** feature, menu, table structure, form field, button, SAP integration, and business logic:

### 1. Primary Source of Truth (CI3 Codebase)
Always inspect CodeIgniter 3 (CI3) files **FIRST** before writing, refactoring, or testing code in Laravel:
- **Controllers**: `c:\xampp\htdocs\epms_ioi_ci4\application\controllers\...`
- **Views**: `c:\xampp\htdocs\epms_ioi_ci4\application\views\...`
- **Models & Queries**: `c:\xampp\htdocs\epms_ioi_ci4\application\models\...`
- **Helpers & SAP Services**: `c:\xampp\htdocs\epms_ioi_ci4\application\helpers\...`

---

### 2. Business Logic & Validation Rules (MANDATORY)
Every Laravel controller must port and implement all validations present in the corresponding CI3 controller:
1. **Form Validations (`$this->form_validation->set_rules`)**:
   - Replicate all field requirements, length checks, format constraints (e.g. dates, regex, numeric).
   - Replicate existence checks (`is_exist` rules).
2. **Duplicate & Integrity Checks**:
   - Check existing records before inserting (e.g. check duplicate combination of Mandor + Employee, Assistant Manager + Division, Field Staff + Gang).
   - Return the exact/matching error message as CI3 (e.g. `"Duplicate Assistant Manager and Division"`, `"Employee already assigned with another gank"`).
3. **Flash Message Matching**:
   - Success and failure flash messages must match CI3 tone and wording (e.g. `"Assistant Manager - Division successfully saved"`).
4. **Active Date Range & Filter Checks**:
   - Whenever fetching dropdowns/masters (e.g. divisions, employees, gangs), ensure active validity filters are applied:
     `valid_from <= current_date` AND `valid_to >= current_date` and scoped to the active estate (`estate_code` / `estate_name`).

---

### 3. Centralization & Multi-Tenancy Architecture
- **Tenant Scope Isolation**:
  - Filter all data queries by `company_id` and estate code prefix (e.g. `1PIP%` for Segamat).
  - Division dropdowns must be filtered by `company_id` AND `estate_code` (`division_estate_code` in CI3) so divisions from other estates within the same company do not appear or duplicate.
- **SQL Server / ODBC Cast Safety**:
  - In SQL Server, never query numeric columns (like `BIGINT id`) directly with non-numeric string values (e.g. employee names) in a single `OR` clause without checking `is_numeric()` first, to prevent `SQLSTATE[22018] cast specification` exceptions.

---

### 4. UI, Presentation & Buttons Consistency (MANDATORY)
- **Top Action Buttons**:
  - Only show buttons that exist in the CI3 view.
  - If CI3 does not have "Get All Data From SAP", only show "Refresh Master Data From SAP".
  - If CI3 has Download Template / Upload Data / Export, display them.
- **Row Action Buttons in Tables**:
  - **CRITICAL**: Check the action column in the CI3 view template (`view.php`).
  - In CI3 grouping modules (`user_assignment`, `assistant_manager_division`, `gang_employee`, `field_staff_gang`), the **Edit button is commented out / does not exist**, only the **Delete (trash)** button is present.
  - **Do NOT render an Edit button in Laravel** if CI3 does not have it.
- **Navigation & Labels**:
  - Menu text, sidebar links, breadcrumbs, and page titles must match CI3 verbatim.
- **Datatable Columns**:
  - Column headers, column ordering, displayed format (e.g. `Code - Name`), must match CI3 view files.
- **Form Design**:
  - Input types (select dropdowns vs auto-suggest inputs) must follow the UX defined in the CI3 form views (`add.php` / `edit.php`).

---

### 5. Verification Checklist Before Completion
Before marking any migrated feature as done:
- [ ] Cross-referenced the CI3 controller method (`save`, `add`, `index`, `update`, `delete`).
- [ ] Cross-referenced the CI3 view template (`view.php`, `add.php`, `edit.php`).
- [ ] Verified top action buttons and per-row action buttons (NO Edit button if CI3 doesn't have it).
- [ ] Validated duplicate prevention logic in store/update.
- [ ] Verified active validity dates and tenant/estate scoping on all dropdowns.
- [ ] Executed and verified a successful test submission in Laravel.
- [ ] Verified error flash messages on duplicate or invalid input.
- [ ] Committed clean, tested code to Git.
