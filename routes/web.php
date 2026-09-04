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
// Masters — batch 3 (SAP + CSV)
use App\Http\Controllers\Admin\Masters\SlocController;
use App\Http\Controllers\Admin\Masters\DestinationController;
use App\Http\Controllers\Admin\Masters\ReceivingPointController;
use App\Http\Controllers\Admin\Masters\GlAccountController;
use App\Http\Controllers\Admin\Masters\GlaOrderController;
use App\Http\Controllers\Admin\Masters\CoconutMaterialController;
use App\Http\Controllers\Admin\Masters\WbsController;
use App\Http\Controllers\Admin\Masters\VraController;
use App\Http\Controllers\Admin\Masters\MeasPointController;
// Masters — batch 4 (multi-column SAP)
use App\Http\Controllers\Admin\Masters\SalesOrderController;
use App\Http\Controllers\Admin\Masters\PurchaseOrderController;
use App\Http\Controllers\Admin\Masters\MaintenanceOrderController;
// Masters — CRUD-only (no SAP/CSV)
use App\Http\Controllers\Admin\Masters\BinController;
use App\Http\Controllers\Admin\Masters\ConfirmationTextController;
use App\Http\Controllers\Admin\Masters\CoconutActivityTypeController;
use App\Http\Controllers\Admin\Masters\OphCardController;
use App\Http\Controllers\Admin\Masters\FdnCardController;
use App\Http\Controllers\Admin\Masters\TphController;
use App\Http\Controllers\Admin\Masters\ReportOphController;
use App\Http\Controllers\Admin\Masters\QrCodeController;
// Masters — Durian (CRUD)
use App\Http\Controllers\Admin\Masters\Durian\VarietyController as DurianVarietyController;
use App\Http\Controllers\Admin\Masters\Durian\GradingController as DurianGradingController;
use App\Http\Controllers\Admin\Masters\Durian\TaskController as DurianTaskController;
use App\Http\Controllers\Admin\Masters\Durian\FertilizerController as DurianFertilizerController;
use App\Http\Controllers\Admin\Masters\Durian\PesticideController as DurianPesticideController;
use App\Http\Controllers\Admin\Masters\Durian\DiseaseController as DurianDiseaseController;
use App\Http\Controllers\Admin\Masters\Durian\SoilConditionController as DurianSoilConditionController;
use App\Http\Controllers\Admin\Masters\Durian\ActivityController as DurianActivityController;
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
// Transaction monitoring (read-only)
use App\Http\Controllers\Transaction\Monitoring\OphMonitoringController;
use App\Http\Controllers\Transaction\Monitoring\AttendanceMonitoringController;
use App\Http\Controllers\Transaction\Monitoring\OvertimeMonitoringController;
use App\Http\Controllers\Transaction\Monitoring\WorkdoneMonitoringController;

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

    // ── Admin routes (CI3 role 1 = admin family: super/country/company/estate admin) ──
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin'])->prefix('admin')->name('admin.')->group(function () {

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

    // ── Masters routes (CI3 role 1 = admin family + IT Staff; company-scoped) ──
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin,it_staff', 'company.scope'])->prefix('masters')->name('masters.')->group(function () {

        // ── Macro: register standard master data routes ───────────────────────
        // Each master: index, datatable, upload/preview/save/cancel (CSV),
        // get-from-sap (Step 1), refresh-from-master (Step 2), staging-info,
        // export (data), generate-csv (template)
        $masterRoutes = function (string $prefix, string $controller, array $extras = []) use (&$masterRoutes) {
            Route::get('/',                    [$controller, 'index'])->name('index');
            Route::get('/datatable',           [$controller, 'getDatatable'])->name('datatable');
            Route::get('/upload',              [$controller, 'upload'])->name('upload');
            Route::post('/preview',            [$controller, 'preview'])->name('preview');
            Route::post('/save-uploaded-data', [$controller, 'saveUploadedData'])->name('save-uploaded-data');
            Route::get('/cancel',              [$controller, 'cancelUpload'])->name('cancel');
            // SAP two-step flow
            Route::post('/get-from-sap',       [$controller, 'getFromSap'])->name('get-from-sap');
            Route::post('/refresh-from-master',[$controller, 'refreshFromMaster'])->name('refresh-from-master');
            Route::get('/staging-info',        [$controller, 'stagingInfo'])->name('staging-info');
            // CSV
            Route::get('/export',              [$controller, 'exportMasterData'])->name('export');
            Route::get('/generate-csv',        [$controller, 'generateCsv'])->name('generate-csv');
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

        // ── Batch 3 masters (SAP/CSV) ─────────────────────────────────────────
        Route::prefix('sloc')->name('sloc.')->group(function () use ($masterRoutes) {
            $masterRoutes('sloc', SlocController::class);
            Route::get('/lookup', [SlocController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('destination')->name('destination.')->group(function () use ($masterRoutes) {
            $masterRoutes('destination', DestinationController::class);
            Route::get('/lookup', [DestinationController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('receiving_point')->name('receiving_point.')->group(function () use ($masterRoutes) {
            $masterRoutes('receiving_point', ReceivingPointController::class);
            Route::get('/lookup', [ReceivingPointController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('gl_account')->name('gl_account.')->group(function () use ($masterRoutes) {
            $masterRoutes('gl_account', GlAccountController::class);
            Route::get('/lookup', [GlAccountController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('gla_order')->name('gla_order.')->group(function () use ($masterRoutes) {
            $masterRoutes('gla_order', GlaOrderController::class);
            Route::get('/lookup', [GlaOrderController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('coconut_material')->name('coconut_material.')->group(function () use ($masterRoutes) {
            $masterRoutes('coconut_material', CoconutMaterialController::class);
            Route::get('/lookup', [CoconutMaterialController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('wbs')->name('wbs.')->group(function () use ($masterRoutes) {
            $masterRoutes('wbs', WbsController::class);
            Route::get('/lookup', [WbsController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('vra')->name('vra.')->group(function () use ($masterRoutes) {
            $masterRoutes('vra', VraController::class);
            Route::get('/lookup', [VraController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('meas_point')->name('meas_point.')->group(function () use ($masterRoutes) {
            $masterRoutes('meas_point', MeasPointController::class);
            Route::get('/lookup', [MeasPointController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('sales_order')->name('sales_order.')->group(function () use ($masterRoutes) {
            $masterRoutes('sales_order', SalesOrderController::class);
            Route::get('/lookup', [SalesOrderController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('purchase_order')->name('purchase_order.')->group(function () use ($masterRoutes) {
            $masterRoutes('purchase_order', PurchaseOrderController::class);
            Route::get('/lookup', [PurchaseOrderController::class, 'lookup'])->name('lookup');
        });
        Route::prefix('maint_order')->name('maint_order.')->group(function () use ($masterRoutes) {
            $masterRoutes('maint_order', MaintenanceOrderController::class);
            Route::get('/lookup', [MaintenanceOrderController::class, 'lookup'])->name('lookup');
        });

        // ── CRUD-only masters (no SAP/CSV) ────────────────────────────────────
        $crudRoutes = function (string $controller) {
            Route::get('/',          [$controller, 'index'])->name('index');
            Route::get('/datatable', [$controller, 'getDatatable'])->name('datatable');
            Route::get('/create',    [$controller, 'create'])->name('create');
            Route::post('/',         [$controller, 'store'])->name('store');
            Route::get('/{id}/edit', [$controller, 'edit'])->name('edit');
            Route::put('/{id}',      [$controller, 'update'])->name('update');
            Route::delete('/{id}',   [$controller, 'destroy'])->name('destroy');
        };

        // ── CRUD + CSV masters (no SAP) — CSV static routes registered BEFORE
        //    the {id} routes so words like "upload" aren't captured as ids. ──
        $crudCsvRoutes = function (string $controller) {
            Route::get('/',                    [$controller, 'index'])->name('index');
            Route::get('/datatable',           [$controller, 'getDatatable'])->name('datatable');
            Route::get('/create',              [$controller, 'create'])->name('create');
            Route::get('/upload',              [$controller, 'upload'])->name('upload');
            Route::post('/preview',            [$controller, 'preview'])->name('preview');
            Route::post('/save-uploaded-data', [$controller, 'saveUploadedData'])->name('save-uploaded-data');
            Route::get('/cancel',              [$controller, 'cancelUpload'])->name('cancel');
            Route::get('/export',              [$controller, 'exportMasterData'])->name('export');
            Route::get('/generate-csv',        [$controller, 'generateCsv'])->name('generate-csv');
            Route::post('/',                   [$controller, 'store'])->name('store');
            Route::get('/{id}/edit',           [$controller, 'edit'])->name('edit');
            Route::put('/{id}',                [$controller, 'update'])->name('update');
            Route::delete('/{id}',             [$controller, 'destroy'])->name('destroy');
        };

        Route::prefix('bin')->name('bin.')->group(fn() => $crudRoutes(BinController::class));
        Route::prefix('confirmation_text')->name('confirmation_text.')->group(fn() => $crudRoutes(ConfirmationTextController::class));
        Route::prefix('coconut_activity_type')->name('coconut_activity_type.')->group(fn() => $crudRoutes(CoconutActivityTypeController::class));
        Route::prefix('oph_card')->name('oph_card.')->group(function () use ($crudCsvRoutes) {
            Route::get('/{id}/print-qr', [OphCardController::class, 'printQr'])->name('print-qr');
            $crudCsvRoutes(OphCardController::class);
        });
        Route::prefix('fdn_card')->name('fdn_card.')->group(function () use ($crudCsvRoutes) {
            Route::get('/{id}/print-qr', [FdnCardController::class, 'printQr'])->name('print-qr');
            $crudCsvRoutes(FdnCardController::class);
        });
        Route::prefix('tph')->name('tph.')->group(function () use ($crudCsvRoutes) {
            Route::get('/{id}/print-qr', [TphController::class, 'printQr'])->name('print-qr');
            $crudCsvRoutes(TphController::class);
        });
        Route::prefix('report_oph')->name('report_oph.')->group(fn() => $crudCsvRoutes(ReportOphController::class));
        Route::get('qrcode', [QrCodeController::class, 'index'])->name('qrcode.index');

        // ── Durian masters (CRUD, durian-enabled companies) ───────────────────
        Route::prefix('durian')->name('durian.')->group(function () use ($crudRoutes) {
            Route::prefix('variety')->name('variety.')->group(fn() => $crudRoutes(DurianVarietyController::class));
            Route::prefix('grading')->name('grading.')->group(fn() => $crudRoutes(DurianGradingController::class));
            Route::prefix('task')->name('task.')->group(fn() => $crudRoutes(DurianTaskController::class));
            Route::prefix('fertilizer')->name('fertilizer.')->group(fn() => $crudRoutes(DurianFertilizerController::class));
            Route::prefix('pesticide')->name('pesticide.')->group(fn() => $crudRoutes(DurianPesticideController::class));
            Route::prefix('disease')->name('disease.')->group(fn() => $crudRoutes(DurianDiseaseController::class));
            Route::prefix('soil_condition')->name('soil_condition.')->group(fn() => $crudRoutes(DurianSoilConditionController::class));
            Route::prefix('activity')->name('activity.')->group(fn() => $crudRoutes(DurianActivityController::class));
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

    // ── Grouping routes (CI3 roles 1,2,3 = admin family + EM + Asst + IT Staff; company-scoped) ──
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin,estate_manager,asst_manager,it_staff', 'company.scope'])->prefix('grouping')->name('grouping.')->group(function () {

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

    // ── Planning routes (CI3 role 3 = Assistant Manager only) ───────────────
    Route::middleware(['roles:asst_manager'])->prefix('planning')->name('planning.')->group(function () {

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

    // ── Approval routes (CI3 role 2 = Estate Manager only) ──────────────────
    Route::middleware(['roles:estate_manager'])->prefix('approval')->name('approval.')->group(function () {

        // Workplan approval is Estate-Manager-only
        Route::prefix('workplan')->name('workplan.')->group(function () {
            Route::get('/',       [WorkplanApprovalController::class, 'index'])->name('index');
            Route::get('/detail', [WorkplanApprovalController::class, 'detail'])->name('detail');
            Route::post('/submit',[WorkplanApprovalController::class, 'submit'])->name('submit');
        });

    });

    // ── Approval routes (CI3 role 3 = Assistant Manager only) ───────────────
    Route::middleware(['roles:asst_manager'])->prefix('approval')->name('approval.')->group(function () {

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

        // Harvesting Chit (Coconut) — Asst Manager (coconut-enabled)
        Route::prefix('coconut-chit')->name('coconut_chit.')->group(function () {
            Route::get('/',        [CoconutHarvestingChitApprovalController::class, 'index'])->name('index');
            Route::get('/{id}',    [CoconutHarvestingChitApprovalController::class, 'detail'])->name('detail');
            Route::post('/submit', [CoconutHarvestingChitApprovalController::class, 'submit'])->name('submit');
        });

    });

    // ── OPH Approval (CI3 roles 2 & 3 = Estate Manager + Assistant Manager) ──
    Route::middleware(['roles:estate_manager,asst_manager'])->prefix('approval')->name('approval.')->group(function () {

        // OPH — Estate Manager (all) or Asst Manager (division-scoped)
        Route::prefix('oph')->name('oph.')->group(function () {
            Route::get('/',        [OphApprovalController::class, 'index'])->name('index');
            Route::get('/{id}',    [OphApprovalController::class, 'detail'])->name('detail');
            Route::post('/submit', [OphApprovalController::class, 'submit'])->name('submit');
        });

    });

    // ── GI Plan (CI3: admin family + Assistant Manager) ─────────────────────
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin,asst_manager'])->prefix('transactions')->name('transactions.')->group(function () {

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

    // ── Transaction Monitoring (read-only): CI3 admin family + managers +
    //    Estate Staff family (Estate Staff, Staff, Plantation Controller,
    //    Company Staff) for oversight. ────────────────────────────────────
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin,estate_manager,asst_manager,estate_staff,staff,pc,cs'])
        ->prefix('transactions/monitoring')->name('transactions.monitoring.')->group(function () {
            $monitor = function (string $prefix, string $controller) {
                Route::prefix($prefix)->name($prefix.'.')->group(function () use ($controller) {
                    Route::get('/',          [$controller, 'index'])->name('index');
                    Route::get('/datatable', [$controller, 'getDatatable'])->name('datatable');
                });
            };
            $monitor('oph',        OphMonitoringController::class);
            $monitor('attendance', AttendanceMonitoringController::class);
            $monitor('overtime',   OvertimeMonitoringController::class);
            $monitor('workdone',   WorkdoneMonitoringController::class);
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

    // ── Retrieve Master Data (CI3 role 1 = admin family) ────────────────────
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin'])
        ->prefix('admin/retrieve-master')->name('admin.retrieve-master.')->group(function () {
        Route::get('/',          [RetrieveMasterDataController::class, 'index'])->name('index');
        Route::post('/sync',     [RetrieveMasterDataController::class, 'sync'])->name('sync');
        Route::post('/sync-all', [RetrieveMasterDataController::class, 'syncAll'])->name('sync-all');
    });

    // ── Delete Pictures (CI3 role 1 = admin family) ────────────────────────
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin'])->prefix('admin/delete-pictures')->name('admin.delete-pictures.')->group(function () {
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
