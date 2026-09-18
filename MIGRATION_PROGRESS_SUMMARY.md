# MIGRATION PROGRESS & SESSION HANDOVER SUMMARY
**CI3 to Laravel Centralized Migration**

- **Primary Reference (Ground Truth / ACUAN)**: `c:\xampp\htdocs\epms_ioi_ci4\application\...`
- **Target Application**: `C:\laragon\www\epms_laravel\...`
- **Environment**: PHP 8.3.4 (`C:\laragon\bin\php\php-8.3.4-Win32-vs16-x64\php.exe`), Laravel 11 / Boost, Windows / Laragon.
- **Last Updated**: September 18, 2026

---

## 1. Golden Rules for Any New Chat Session (MANDATORY)

1. **CI3 is the Ground Truth (ACUAN MUTLAK)**:
   - Always open and read CI3 controller, view, and custom JS files before making or refactoring anything in Laravel.
   - Match **Toolbar Buttons** (top action buttons) exactly with CI3 `view.php`.
   - Match **Datatable Columns**, checkboxes, and sort orders with CI3 `view.php` and `table.js`.
   - Match **Per-row Action Buttons** (Edit, Delete, QR, etc.) in the action column.
     - **Important**: In Grouping modules (e.g., `mandor_employee`, `field_assistant_division`, `gang_employee`), CI3 has **NO Edit button** (only Delete).
     - In Master modules (e.g., `masters/tph` / Task, `masters/employee`), CI3 **HAS Edit buttons** (`hasEdit = true`).
   - Match all **Form Validations**, duplicate checks, date-range filters, and flash messages verbatim.

2. **Multi-Tenant Scoping & Centralized Architecture**:
   - Scope queries by `company_id` and active `estate_code` (e.g. Segamat `SM`).
   - Scope dropdown lists for active validity dates: `valid_from <= today AND valid_to >= today`.
   - SQL Server / ODBC casting safety: Never compare numeric columns (e.g. `id`) with arbitrary strings in `OR` clauses without checking `is_numeric()`.

3. **File Editing Pattern on Windows / IDE Policy**:
   - Direct file creation or modification inside `C:\laragon\www\epms_laravel\` may be blocked by IDE filesystem permission policy.
   - **Established Solution**: Write files or scripts into workspace `c:\xampp\htdocs\epms_ioi_ci4\...`, then use PowerShell `Copy-Item` to copy into `C:\laragon\www\epms_laravel\...`.
   - PHP commands should use: `& "C:\laragon\bin\php\php-8.3.4-Win32-vs16-x64\php.exe" artisan ...` in working directory `C:\laragon\www\epms_laravel`.

---

## 2. Recent Progress & Changes Completed

### A. Master Task / TPH (`/masters/tph`) — 100% CI3 Match
- **Reference**: `masters/Task.php`, `views/main/master/task/view.php`, `table.js`.
- **Top Action Buttons**:
  - `Upload Data` (`/masters/tph/upload`)
  - `Generate QR` (Bulk print selected TPH via modal/form submit)
  - `Download Template` (`/masters/tph/template`)
  - `Export Master Data` (`/masters/tph/export`)
  - `+ Add Task (TPH)` (`/masters/tph/create`)
- **Table & Bulk Checkboxes**:
  - Column 0: `#check_all` checkbox in header, `tph_id[]` checkbox on each row.
- **Bulk & Single QR Printing**:
  - Route: `POST /masters/tph/generate-qr` (`masters.tph.generate-qr`).
  - Controller: `TphController::generateQr(Request $request)`.
  - View: `resources/views/admin/masters/tph/print_qr.blade.php`.
  - QR Format matching CI3: `EST:{estate};BLOCK:{block};PLATFORM:{platform};TASK:{task}`.
- **Row Action Buttons**:
  - Edit button enabled (`hasEdit(): bool { return true; }`), pointing to `/masters/tph/{id}/edit`.
  - Delete button enabled.
  - Per-row Print QR shortcut button enabled.
- **Palm Exceed Warning**:
  - Calculates whether total palms in block exceed block capacity (`total_palm_exceed`), displaying warning banner if true.
- **Commit**: `commit 9cdb206`.

### B. Global RouteNotFoundException Fix Across 46 Master/Grouping Views
- **Problem**: In shared master/grouping views (`resources/views/admin/grouping/_shared/index.blade.php` and `resources/views/admin/masters/_shared/index.blade.php`), calling non-existent SAP or CSV routes crashed 40+ CRUD-only masters.
- **Fixes**:
  - Wrapped SAP button routes with `Route::has(...)`. (Commit `9866482`).
  - Wrapped CSV button routes (`upload`, `generate-csv`, `export`) with `Route::has(...)`. (Commit `fe28960`).
  - Updated `BaseGroupingController.php`:
    ```php
    protected function hasCsv(): bool {
        return in_array(HandlesCsvMaster::class, class_uses_recursive(static::class));
    }
    ```
  - Result: All 46 master & grouping index routes render cleanly without missing route exceptions.

### C. Grouping Modules Audit & Fixes
1. **Mandor - Employee (`/grouping/mandor_employee`)**:
   - Mapped to `t_user_assignment` matching CI3. Form matches `FORM ADD GROUPING GANG`. Duplicate validations & SAP sync implemented. (Commit `2d267a1`).
2. **Assistant Manager - Division (`/grouping/field_assistant_division`)**:
   - Filtered dropdowns by active `estate_code` (`SM`). Duplicate validation message matches CI3: `"Duplicate Assistant Manager and Division"`. (Commits `f8b1890`, `958db4a`, `1f19212`).
3. **Action Buttons**:
   - Removed Edit buttons from all Grouping index tables to follow CI3. Retained Delete buttons with robust modal triggers. (Commits `b3adb9a`, `cb021b4`).

---

## 3. Key Reference File Mapping

| Feature | CI3 Ground Truth Path | Laravel Path |
| :--- | :--- | :--- |
| **Task / TPH Controller** | `application/controllers/masters/Task.php` | `app/Http/Controllers/Admin/Masters/TphController.php` |
| **Task / TPH Index View** | `application/views/main/master/task/view.php` | `resources/views/admin/masters/tph/index.blade.php` |
| **Task / TPH QR View** | `application/views/main/master/task/print_qr.php` | `resources/views/admin/masters/tph/print_qr.blade.php` |
| **Task / TPH Table JS** | `assets/js/custom_js/master_data/task/table.js` | Embedded in `resources/views/admin/masters/tph/index.blade.php` |
| **Shared Masters Index** | `application/views/main/master/...` | `resources/views/admin/masters/_shared/index.blade.php` |
| **Shared Grouping Index** | `application/views/main/grouping/...` | `resources/views/admin/grouping/_shared/index.blade.php` |
| **Mandor - Employee** | `application/controllers/grouping/User_assignment.php` | `app/Http/Controllers/Admin/Grouping/MandorEmployeeController.php` |
| **AM - Division** | `application/controllers/grouping/Assistant_manager_division.php` | `app/Http/Controllers/Admin/Grouping/FieldAssistantDivisionController.php` |
| **Gang - Employee** | `application/controllers/grouping/Gang_employee.php` | `app/Http/Controllers/Admin/Grouping/GangEmployeeController.php` |
| **Field Staff - Gang** | `application/controllers/grouping/Field_staff_gang.php` | `app/Http/Controllers/Admin/Grouping/FieldStaffGangController.php` |

---

## 4. Checklist / Next Steps for Incoming Session

When resuming or continuing migration:
1. **Identify the Target Menu**:
   - Inquire with user or proceed to the next unverified Master Data or Grouping menu (e.g. `masters/employee`, `masters/block`, `masters/estate`, `grouping/gang_employee`, `grouping/field_staff_gang`).
2. **Perform CI3 Comparison Audit**:
   - [ ] Inspect CI3 `view.php` for top toolbar buttons.
   - [ ] Inspect CI3 `table.js` and `view.php` for table columns, checkbox columns, and sorting.
   - [ ] Check action buttons per row (Does CI3 have Edit? If not, set `hasEdit(): bool { return false; }`).
   - [ ] Inspect CI3 controller (`save()`, `update()`, `delete()`) for all validation constraints and duplicate messages.
3. **Execute and Verify**:
   - Run verification test script using Laravel artisan / PHP test runner.
   - Ensure clean Git commit on `C:\laragon\www\epms_laravel`.
