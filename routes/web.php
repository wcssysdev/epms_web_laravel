<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ConfigController;
// Grouping
use App\Http\Controllers\Admin\Grouping\GangEmployeeController;
use App\Http\Controllers\Admin\Grouping\FieldStaffController;
use App\Http\Controllers\Admin\Grouping\MandorEmployeeController;
use App\Http\Controllers\Admin\Grouping\FieldAssistantDivisionController;
// Sprint 6 Utilities
use App\Http\Controllers\Admin\AuditTrailController;
use App\Http\Controllers\Admin\RetrieveMasterDataController;
use App\Http\Controllers\Admin\DeletePicturesController;
// Masters — company-scoped
use App\Http\Controllers\Admin\Masters\EstateController;
use App\Http\Controllers\Admin\Masters\DivisionController;
use App\Http\Controllers\Admin\Masters\BlockController;
use App\Http\Controllers\Admin\Masters\EmployeeController;
use App\Http\Controllers\Admin\Masters\ActivityController;
use App\Http\Controllers\Admin\Masters\VendorController;
use App\Http\Controllers\Admin\Masters\MaterialController;
use App\Http\Controllers\Admin\Masters\DeviceController;
use App\Http\Controllers\Admin\Masters\WorktypeController;
use App\Http\Controllers\Admin\Masters\WorkCenterController;
use App\Http\Controllers\Admin\Masters\CostCenterController;
// Masters — global lookups
use App\Http\Controllers\Admin\Masters\AttendanceController;
use App\Http\Controllers\Admin\Masters\UomController;
use App\Http\Controllers\Admin\Masters\HarvestMethodController;
use App\Http\Controllers\Admin\Masters\MovementTypeController;
// Planning (Estate Manager 40 + Asst Manager 50)
use App\Http\Controllers\Planning\WorkplanController;
use App\Http\Controllers\Planning\HarvestingPlanController;
// Approval (Estate Manager 40)
use App\Http\Controllers\Approval\WorkplanApprovalController;
use App\Http\Controllers\Approval\OvertimeApprovalController;
use App\Http\Controllers\Approval\UnplannedActivityApprovalController;
use App\Http\Controllers\Approval\OphApprovalController;
use App\Http\Controllers\Approval\CoconutHarvestingChitApprovalController;
use App\Http\Controllers\Planning\CoconutHarvestingPlanController;
// Transactions
use App\Http\Controllers\Transaction\GiPlanController;

// ──────────────────────────────────────────────────────────────────────────────
// PUBLIC — Auth routes (no auth required)
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login',[LoginController::class, 'login'])->name('login.post');
    Route::get('/',      fn() => redirect()->route('login'));
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth.check')
    ->name('logout');

// ──────────────────────────────────────────────────────────────────────────────
// PROTECTED — All authenticated routes
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth.check'])->group(function () {

    // Change Password (accessible to all roles)
    Route::get('/change-password',  [ChangePasswordController::class, 'index'])->name('change-password');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('change-password.post');

    // ── Dashboard ──────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/home',      [DashboardController::class, 'index'])->name('home');

    // ── Admin routes (Company Admin level 30 and above) ────────────────────
    Route::middleware(['role:30'])->prefix('admin')->name('admin.')->group(function () {

        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',                         [UserController::class, 'index'])->name('index');
            Route::get('/datatable',                [UserController::class, 'getDatatable'])->name('datatable');
            Route::get('/create',                   [UserController::class, 'create'])->name('create');
            Route::post('/',                        [UserController::class, 'store'])->name('store');
            Route::get('/{user}/edit',              [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}',                   [UserController::class, 'update'])->name('update');
            Route::post('/{user}/reset-password',   [UserController::class, 'resetPassword'])->name('reset-password');
            Route::post('/{user}/toggle-active',    [UserController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{user}/reset-session',    [UserController::class, 'resetSession'])->name('reset-session');
        });

        // Estate Settings / Config
        Route::prefix('config')->name('config.')->group(function () {
            Route::get('/',             [ConfigController::class, 'index'])->name('index');
            Route::put('/',             [ConfigController::class, 'update'])->name('update');
            Route::post('/test-sap',    [ConfigController::class, 'testSapConnection'])->name('test-sap');
            Route::post('/toggle-lock', [ConfigController::class, 'toggleSystemLock'])->name('toggle-lock');
        });

    });

    // ── Masters routes (Estate Manager level 40 and above, plus IT Staff) ──
    Route::middleware(['role:40,it_staff'])->prefix('masters')->name('masters.')->group(function () {

        // ── Macro: register standard master data routes ───────────────────────
        // Each master: index, datatable, upload, preview, save, cancel, replace, get-data, generate-csv
        $masterRoutes = function (string $prefix, string $controller, array $extras = []) use (&$masterRoutes) {
            Route::get('/',                   [$controller, 'index'])->name('index');
            Route::get('/datatable',          [$controller, 'getDatatable'])->name('datatable');
            Route::get('/upload',             [$controller, 'upload'])->name('upload');
            Route::post('/preview',           [$controller, 'preview'])->name('preview');
            Route::post('/save-uploaded-data',[$controller, 'saveUploadedData'])->name('save-uploaded-data');
            Route::get('/cancel',             [$controller, 'cancelUpload'])->name('cancel');
            Route::post('/replace-master-data',[$controller,'replaceMasterData'])->name('replace-master-data');
            Route::get('/get-master-data',    [$controller, 'getMasterData'])->name('get-master-data');
            Route::get('/generate-csv',       [$controller, 'generateCsv'])->name('generate-csv');
        };

        // ── Estate ────────────────────────────────────────────────────────────
        Route::prefix('estate')->name('estate.')->group(function () use ($masterRoutes) {
            $masterRoutes('estate', EstateController::class);
            Route::get('/lookup', [EstateController::class, 'lookup'])->name('lookup');
        });

        // ── Division ──────────────────────────────────────────────────────────
        Route::prefix('division')->name('division.')->group(function () use ($masterRoutes) {
            $masterRoutes('division', DivisionController::class);
            Route::get('/by-estate/{estateCode}', [DivisionController::class, 'getByEstate'])->name('by-estate');
        });

        // ── Block ─────────────────────────────────────────────────────────────
        Route::prefix('block')->name('block.')->group(function () use ($masterRoutes) {
            $masterRoutes('block', BlockController::class);
            Route::get('/by-division/{estateCode}/{divisionCode}', [BlockController::class, 'getByDivision'])->name('by-division');
        });

        // ── Employee ──────────────────────────────────────────────────────────
        Route::prefix('employee')->name('employee.')->group(function () use ($masterRoutes) {
            $masterRoutes('employee', EmployeeController::class);
            Route::post('/generate-qr',  [EmployeeController::class, 'generateQr'])->name('generate-qr');
            Route::get('/lookup',        [EmployeeController::class, 'lookup'])->name('lookup');
        });

        // ── Activity ──────────────────────────────────────────────────────────
        Route::prefix('activity')->name('activity.')->group(function () use ($masterRoutes) {
            $masterRoutes('activity', ActivityController::class);
        });

        // ── Vendor ────────────────────────────────────────────────────────────
        Route::prefix('vendor')->name('vendor.')->group(function () use ($masterRoutes) {
            $masterRoutes('vendor', VendorController::class);
            Route::post('/generate-qr', [VendorController::class, 'generateQr'])->name('generate-qr');
            Route::get('/lookup',       [VendorController::class, 'lookup'])->name('lookup');
        });

        // ── Material ──────────────────────────────────────────────────────────
        Route::prefix('material')->name('material.')->group(function () use ($masterRoutes) {
            $masterRoutes('material', MaterialController::class);
            Route::post('/generate-qr', [MaterialController::class, 'generateQr'])->name('generate-qr');
            Route::get('/lookup',       [MaterialController::class, 'lookup'])->name('lookup');
        });

        // ── Device ────────────────────────────────────────────────────────────
        Route::prefix('device')->name('device.')->group(function () use ($masterRoutes) {
            $masterRoutes('device', DeviceController::class);
            Route::get('/add',         [DeviceController::class, 'add'])->name('add');
            Route::post('/save',       [DeviceController::class, 'save'])->name('save');
            Route::get('/{id}/edit',   [DeviceController::class, 'edit'])->name('edit');
            Route::put('/{id}',        [DeviceController::class, 'update'])->name('update');
            Route::delete('/{id}',     [DeviceController::class, 'destroy'])->name('destroy');
        });

        // ── Worktype ──────────────────────────────────────────────────────────
        Route::prefix('worktype')->name('worktype.')->group(function () use ($masterRoutes) {
            $masterRoutes('worktype', WorktypeController::class);
        });

        // ── Work Center ───────────────────────────────────────────────────────
        Route::prefix('work_center')->name('work_center.')->group(function () use ($masterRoutes) {
            $masterRoutes('work_center', WorkCenterController::class);
            Route::get('/lookup', [WorkCenterController::class, 'lookup'])->name('lookup');
        });

        // ── Cost Center ───────────────────────────────────────────────────────
        Route::prefix('cost_center')->name('cost_center.')->group(function () use ($masterRoutes) {
            $masterRoutes('cost_center', CostCenterController::class);
            Route::get('/lookup', [CostCenterController::class, 'lookup'])->name('lookup');
        });

        // ── Global Lookups (Super/Country Admin manage, all roles read) ───────
        Route::prefix('global')->name('global.')->group(function () {

            // Reusable macro for global CRUD
            $globalRoutes = function (string $controller) {
                Route::get('/',           [$controller, 'index'])->name('index');
                Route::get('/datatable',  [$controller, 'getDatatable'])->name('datatable');
                Route::get('/add',        [$controller, 'add'])->name('add');
                Route::post('/save',      [$controller, 'save'])->name('save');
                Route::get('/{id}/edit',  [$controller, 'edit'])->name('edit');
                Route::put('/{id}',       [$controller, 'update'])->name('update');
                Route::delete('/{id}',    [$controller, 'destroy'])->name('destroy');
                Route::get('/generate-csv', [$controller, 'generateCsv'])->name('generate-csv');
                Route::get('/lookup',     [$controller, 'lookup'])->name('lookup');
            };

            Route::prefix('attendance')->name('attendance.')->group(fn() => $globalRoutes(AttendanceController::class));
            Route::prefix('uom')->name('uom.')->group(fn() => $globalRoutes(UomController::class));
            Route::prefix('harvest_method')->name('harvest_method.')->group(fn() => $globalRoutes(HarvestMethodController::class));
            Route::prefix('movement_type')->name('movement_type.')->group(fn() => $globalRoutes(MovementTypeController::class));

        });

    });

    // ── Grouping routes (Asst Manager 50 and above, plus IT Staff) ─────────
    Route::middleware(['role:50,it_staff'])->prefix('grouping')->name('grouping.')->group(function () {

        // Reusable macro for grouping CRUD
        $groupRoutes = function (string $controller) {
            Route::get('/',           [$controller, 'index'])->name('index');
            Route::get('/datatable',  [$controller, 'getDatatable'])->name('datatable');
            Route::get('/create',     [$controller, 'create'])->name('create');
            Route::post('/',          [$controller, 'store'])->name('store');
            Route::get('/{id}/edit',  [$controller, 'edit'])->name('edit');
            Route::put('/{id}',       [$controller, 'update'])->name('update');
            Route::delete('/{id}',    [$controller, 'destroy'])->name('destroy');
        };

        Route::prefix('gang_employee')->name('gang_employee.')->group(function () use ($groupRoutes) {
            $groupRoutes(GangEmployeeController::class);
            Route::get('/gang-codes', [GangEmployeeController::class, 'gangCodes'])->name('gang-codes');
        });

        Route::prefix('field_staff')->name('field_staff.')->group(function () use ($groupRoutes) {
            $groupRoutes(FieldStaffController::class);
        });

        Route::prefix('mandor_employee')->name('mandor_employee.')->group(function () use ($groupRoutes) {
            $groupRoutes(MandorEmployeeController::class);
        });

        Route::prefix('field_assistant_division')->name('field_assistant_division.')->group(function () use ($groupRoutes) {
            $groupRoutes(FieldAssistantDivisionController::class);
            Route::get('/managers', [FieldAssistantDivisionController::class, 'managers'])->name('managers');
        });

    });

    // ── Planning routes (Asst Manager 50 + Estate Manager 40) ───────────────
    Route::middleware(['role:50'])->prefix('planning')->name('planning.')->group(function () {

        // ── Workplan ──────────────────────────────────────────────────────────
        Route::prefix('workplan')->name('workplan.')->group(function () {
            Route::get('/',              [WorkplanController::class, 'index'])->name('index');
            Route::get('/create',        [WorkplanController::class, 'create'])->name('create');
            Route::post('/',             [WorkplanController::class, 'store'])->name('store');
            Route::get('/{id}',          [WorkplanController::class, 'show'])->name('show');
            Route::get('/{id}/edit',     [WorkplanController::class, 'edit'])->name('edit');
            Route::put('/{id}',          [WorkplanController::class, 'update'])->name('update');
            Route::delete('/{id}',       [WorkplanController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/publish', [WorkplanController::class, 'publish'])->name('publish');
            // AJAX helpers
            Route::get('/ajax/blocks',     [WorkplanController::class, 'getBlocks'])->name('blocks');
            Route::get('/ajax/activities', [WorkplanController::class, 'getActivities'])->name('activities');
            Route::get('/ajax/block-info', [WorkplanController::class, 'getBlockInfo'])->name('block-info');
            Route::get('/ajax/materials',  [WorkplanController::class, 'searchMaterials'])->name('materials');
        });

        // ── Harvesting Plan ─────────────────────────────────────────────────
        Route::prefix('harvesting-plan')->name('harvesting_plan.')->group(function () {
            Route::get('/',              [HarvestingPlanController::class, 'index'])->name('index');
            Route::get('/create',        [HarvestingPlanController::class, 'create'])->name('create');
            Route::post('/',             [HarvestingPlanController::class, 'store'])->name('store');
            Route::get('/{id}/edit',     [HarvestingPlanController::class, 'edit'])->name('edit');
            Route::put('/{id}',          [HarvestingPlanController::class, 'update'])->name('update');
            Route::delete('/{id}',       [HarvestingPlanController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/approve', [HarvestingPlanController::class, 'approve'])->name('approve');
            Route::get('/ajax/blocks',   [HarvestingPlanController::class, 'getBlocks'])->name('blocks');
        });

        // ── Coconut Harvesting Plan (coconut-enabled companies) ─────────────
        Route::prefix('coconut-harvesting-plan')->name('coconut_harvesting_plan.')->group(function () {
            Route::get('/',              [CoconutHarvestingPlanController::class, 'index'])->name('index');
            Route::get('/create',        [CoconutHarvestingPlanController::class, 'create'])->name('create');
            Route::post('/',             [CoconutHarvestingPlanController::class, 'store'])->name('store');
            Route::get('/{id}/edit',     [CoconutHarvestingPlanController::class, 'edit'])->name('edit');
            Route::put('/{id}',          [CoconutHarvestingPlanController::class, 'update'])->name('update');
            Route::delete('/{id}',       [CoconutHarvestingPlanController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/approve', [CoconutHarvestingPlanController::class, 'approve'])->name('approve');
            Route::get('/ajax/blocks',   [CoconutHarvestingPlanController::class, 'getBlocks'])->name('blocks');
        });

    });

    // ── Approval routes (Estate Manager 40) ─────────────────────────────────
    Route::middleware(['role:40'])->prefix('approval')->name('approval.')->group(function () {

        // Workplan approval is Estate-Manager-only
        Route::prefix('workplan')->name('workplan.')->group(function () {
            Route::get('/',       [WorkplanApprovalController::class, 'index'])->name('index');
            Route::get('/detail', [WorkplanApprovalController::class, 'detail'])->name('detail');
            Route::post('/submit',[WorkplanApprovalController::class, 'submit'])->name('submit');
        });

    });

    // ── Approval routes shared with Assistant Manager (level 50) ────────────
    Route::middleware(['role:50'])->prefix('approval')->name('approval.')->group(function () {

        // Overtime — Asst Manager (division-scoped)
        Route::prefix('overtime')->name('overtime.')->group(function () {
            Route::get('/',        [OvertimeApprovalController::class, 'index'])->name('index');
            Route::post('/submit', [OvertimeApprovalController::class, 'submit'])->name('submit');
        });

        // Unplanned Activity — Asst Manager (division-scoped)
        Route::prefix('unplanned-activity')->name('unplanned_activity.')->group(function () {
            Route::get('/',            [UnplannedActivityApprovalController::class, 'index'])->name('index');
            Route::get('/{id}',        [UnplannedActivityApprovalController::class, 'detail'])->name('detail');
            Route::post('/submit',     [UnplannedActivityApprovalController::class, 'submit'])->name('submit');
        });

        // OPH — Estate Manager (all) or Asst Manager (division-scoped)
        Route::prefix('oph')->name('oph.')->group(function () {
            Route::get('/',        [OphApprovalController::class, 'index'])->name('index');
            Route::get('/{id}',    [OphApprovalController::class, 'detail'])->name('detail');
            Route::post('/submit', [OphApprovalController::class, 'submit'])->name('submit');
        });

        // Harvesting Chit (Coconut) — Estate/Asst Manager (coconut-enabled)
        Route::prefix('coconut-chit')->name('coconut_chit.')->group(function () {
            Route::get('/',        [CoconutHarvestingChitApprovalController::class, 'index'])->name('index');
            Route::get('/{id}',    [CoconutHarvestingChitApprovalController::class, 'detail'])->name('detail');
            Route::post('/submit', [CoconutHarvestingChitApprovalController::class, 'submit'])->name('submit');
        });

    });

    // ── Transactions routes (Asst Manager 50 and above) ─────────────────────
    Route::middleware(['role:50'])->prefix('transactions')->name('transactions.')->group(function () {

        // ── GI Plan (Goods Issue Plan) ──────────────────────────────────────
        Route::prefix('gi-plan')->name('gi_plan.')->group(function () {
            Route::get('/',              [GiPlanController::class, 'index'])->name('index');
            Route::get('/create',        [GiPlanController::class, 'create'])->name('create');
            Route::post('/',             [GiPlanController::class, 'store'])->name('store');
            Route::get('/{id}',          [GiPlanController::class, 'show'])->name('show');
            Route::get('/{id}/edit',     [GiPlanController::class, 'edit'])->name('edit');
            Route::put('/{id}',          [GiPlanController::class, 'update'])->name('update');
            Route::delete('/{id}',       [GiPlanController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/publish', [GiPlanController::class, 'publish'])->name('publish');
            Route::post('/{id}/approve', [GiPlanController::class, 'approve'])->name('approve');
            Route::get('/ajax/materials',[GiPlanController::class, 'searchMaterials'])->name('materials');
        });

    });

    // ── Reporting routes ───────────────────────────────────────────────────
    Route::prefix('reporting')->name('reporting.')->group(function () {
        // TODO Sprint 7+
    });

    // ── Audit Trail ────────────────────────────────────────────────────────
    Route::prefix('admin/audit')->name('admin.audit.')->group(function () {
        Route::get('/',          [AuditTrailController::class, 'index'])->name('index');
        Route::get('/datatable', [AuditTrailController::class, 'getDatatable'])->name('datatable');
    });

    // ── Retrieve Master Data ────────────────────────────────────────────────
    Route::prefix('admin/retrieve-master')->name('admin.retrieve-master.')->group(function () {
        Route::get('/',          [RetrieveMasterDataController::class, 'index'])->name('index');
        Route::post('/sync',     [RetrieveMasterDataController::class, 'sync'])->name('sync');
        Route::post('/sync-all', [RetrieveMasterDataController::class, 'syncAll'])->name('sync-all');
    });

    // ── Delete Pictures (Company Admin+) ───────────────────────────────────
    Route::middleware(['role:30'])->prefix('admin/delete-pictures')->name('admin.delete-pictures.')->group(function () {
        Route::get('/',        [DeletePicturesController::class, 'index'])->name('index');
        Route::post('/count',  [DeletePicturesController::class, 'count'])->name('count');
        Route::post('/delete', [DeletePicturesController::class, 'delete'])->name('delete');
    });

});

// ──────────────────────────────────────────────────────────────────────────────
// SUPER ADMIN routes
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth.check', 'role:10'])->prefix('super-admin')->name('super-admin.')->group(function () {
    // TODO Sprint 1: Country, Company management
});

// ──────────────────────────────────────────────────────────────────────────────
// COUNTRY ADMIN routes
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth.check', 'role:20'])->prefix('country-admin')->name('country-admin.')->group(function () {
    // TODO Sprint 1: Cross-company views
});
