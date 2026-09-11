<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Dashboard\HarvestingController as DashboardHarvestingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\Approval\SubstitutionController;
use App\Http\Controllers\Admin\Approval\MasterDataSubstitutionController;
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
// Transaction operational entry (Estate Staff)
use App\Http\Controllers\Transaction\AttendanceEntryController;
use App\Http\Controllers\Transaction\WorkdoneEntryController;
use App\Http\Controllers\Transaction\HarvesterAssignmentController;
use App\Http\Controllers\Transaction\GeneralWorkerAssignmentController;
use App\Http\Controllers\Transaction\OvertimeEntryController;
use App\Http\Controllers\Transaction\VraEntryController;
use App\Http\Controllers\Transaction\PlatformCheckingController;
use App\Http\Controllers\Transaction\OphEntryController;
use App\Http\Controllers\Transaction\OphMillGraderController;
use App\Http\Controllers\Transaction\Checkpoint1Controller;
use App\Http\Controllers\Transaction\Checkpoint2Controller;
use App\Http\Controllers\Transaction\FdnController;
use App\Http\Controllers\Transaction\CoconutHarvestingChitController;
use App\Http\Controllers\Transaction\CoconutFdnController;
use App\Http\Controllers\Transaction\CheckpointCoconutController;
use App\Http\Controllers\Transaction\GradingCoconutController;
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
    
    // Dashboard Harvesting (roles: admin, estate_manager, asst_manager, estate_staff)
    Route::middleware(['roles:super_admin,country_admin,company_admin,admin,estate_manager,asst_manager,estate_staff'])
         ->get('/dashboard/harvesting', [DashboardHarvestingController::class, 'index'])
         ->name('dashboard.harvesting');

    // ── Reporting routes (roles: estate_manager, asst_manager, estate_staff) ──────
    Route::middleware(['roles:estate_manager,asst_manager,estate_staff'])->prefix('reporting')->name('reporting.')->group(function () {
        
        // Audit Trail
        Route::prefix('audit-trail')->name('audit-trail.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Reporting\AuditTrailController::class, 'index'])->name('index');
            Route::get('/export',              [\App\Http\Controllers\Reporting\AuditTrailController::class, 'export'])->name('export');
            Route::get('/{id}/{type}/detail',  [\App\Http\Controllers\Reporting\AuditTrailController::class, 'detail'])->name('detail');
        });
        
        // Transaction Reports
        Route::prefix('transaction')->name('transaction.')->group(function () {
            
            // Workdone Report
            Route::get('/workdone',         [\App\Http\Controllers\Reporting\Transaction\WorkdoneReportController::class, 'index'])->name('workdone.index');
            Route::get('/workdone/export',  [\App\Http\Controllers\Reporting\Transaction\WorkdoneReportController::class, 'export'])->name('workdone.export');
            
            // Attendance Report
            Route::get('/attendance',        [\App\Http\Controllers\Reporting\Transaction\AttendanceReportController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/export', [\App\Http\Controllers\Reporting\Transaction\AttendanceReportController::class, 'export'])->name('attendance.export');
            
            // OPH Report
            Route::get('/oph',        [\App\Http\Controllers\Reporting\Transaction\OphReportController::class, 'index'])->name('oph.index');
            Route::get('/oph/export', [\App\Http\Controllers\Reporting\Transaction\OphReportController::class, 'export'])->name('oph.export');
            
            // OPH Summary Report
            Route::get('/oph-summary',        [\App\Http\Controllers\Reporting\Transaction\OphSummaryReportController::class, 'index'])->name('oph-summary.index');
            Route::get('/oph-summary/export', [\App\Http\Controllers\Reporting\Transaction\OphSummaryReportController::class, 'export'])->name('oph-summary.export');
            
            // Overtime Report
            Route::get('/overtime',        [\App\Http\Controllers\Reporting\Transaction\OvertimeReportController::class, 'index'])->name('overtime.index');
            Route::get('/overtime/export', [\App\Http\Controllers\Reporting\Transaction\OvertimeReportController::class, 'export'])->name('overtime.export');
            
            // Coconut Chit Report
            Route::get('/coconut-chit',        [\App\Http\Controllers\Reporting\Transaction\CoconutChitReportController::class, 'index'])->name('coconut-chit.index');
            Route::get('/coconut-chit/export', [\App\Http\Controllers\Reporting\Transaction\CoconutChitReportController::class, 'export'])->name('coconut-chit.export');
            
            // Daily OPH Report
            Route::get('/daily-oph',        [\App\Http\Controllers\Reporting\Transaction\DailyOphReportController::class, 'index'])->name('daily-oph.index');
            Route::get('/daily-oph/export', [\App\Http\Controllers\Reporting\Transaction\DailyOphReportController::class, 'export'])->name('daily-oph.export');
            
            // OPH by Division Report
            Route::get('/oph-by-division',        [\App\Http\Controllers\Reporting\Transaction\OphByDivisionReportController::class, 'index'])->name('oph-by-division.index');
            Route::get('/oph-by-division/export', [\App\Http\Controllers\Reporting\Transaction\OphByDivisionReportController::class, 'export'])->name('oph-by-division.export');
            
            // Summary Attendance Report
            Route::get('/summary-attendance',        [\App\Http\Controllers\Reporting\Transaction\SummaryAttendanceReportController::class, 'index'])->name('summary-attendance.index');
            Route::get('/summary-attendance/export', [\App\Http\Controllers\Reporting\Transaction\SummaryAttendanceReportController::class, 'export'])->name('summary-attendance.export');
            
            // Coconut Chit Grading Report
            Route::get('/coconut-chit-grading',        [\App\Http\Controllers\Reporting\Transaction\CoconutChitGradingReportController::class, 'index'])->name('coconut-chit-grading.index');
            Route::get('/coconut-chit-grading/export', [\App\Http\Controllers\Reporting\Transaction\CoconutChitGradingReportController::class, 'export'])->name('coconut-chit-grading.export');
            
            // FDN Coconut Report
            Route::get('/fdn-coconut',        [\App\Http\Controllers\Reporting\Transaction\FdnCoconutReportController::class, 'index'])->name('fdn-coconut.index');
            Route::get('/fdn-coconut/export', [\App\Http\Controllers\Reporting\Transaction\FdnCoconutReportController::class, 'export'])->name('fdn-coconut.export');
            
            // CP Report
            Route::get('/cp',        [\App\Http\Controllers\Reporting\Transaction\CpReportController::class, 'index'])->name('cp.index');
            Route::get('/cp/export', [\App\Http\Controllers\Reporting\Transaction\CpReportController::class, 'export'])->name('cp.export');
            
            // FDN Report
            Route::get('/fdn',        [\App\Http\Controllers\Reporting\Transaction\FdnReportController::class, 'index'])->name('fdn.index');
            Route::get('/fdn/export', [\App\Http\Controllers\Reporting\Transaction\FdnReportController::class, 'export'])->name('fdn.export');
            
            // GI-R Report
            Route::get('/gi-r',        [\App\Http\Controllers\Reporting\Transaction\GiRReportController::class, 'index'])->name('gi-r.index');
            Route::get('/gi-r/export', [\App\Http\Controllers\Reporting\Transaction\GiRReportController::class, 'export'])->name('gi-r.export');
            
            // GR-R Report
            Route::get('/gr-r',        [\App\Http\Controllers\Reporting\Transaction\GrRReportController::class, 'index'])->name('gr-r.index');
            Route::get('/gr-r/export', [\App\Http\Controllers\Reporting\Transaction\GrRReportController::class, 'export'])->name('gr-r.export');
            
            // VRA Report
            Route::get('/vra',        [\App\Http\Controllers\Reporting\Transaction\VraReportController::class, 'index'])->name('vra.index');
            Route::get('/vra/export', [\App\Http\Controllers\Reporting\Transaction\VraReportController::class, 'export'])->name('vra.export');
            
            // Backlog Report
            Route::get('/backlog',        [\App\Http\Controllers\Reporting\Transaction\BacklogReportController::class, 'index'])->name('backlog.index');
            Route::get('/backlog/export', [\App\Http\Controllers\Reporting\Transaction\BacklogReportController::class, 'export'])->name('backlog.export');
            
            // Backlog Coconut Report
            Route::get('/backlog-coconut',        [\App\Http\Controllers\Reporting\Transaction\BacklogCoconutReportController::class, 'index'])->name('backlog-coconut.index');
            Route::get('/backlog-coconut/export', [\App\Http\Controllers\Reporting\Transaction\BacklogCoconutReportController::class, 'export'])->name('backlog-coconut.export');
            
            // General Allocation Report
            Route::get('/general-allocation',        [\App\Http\Controllers\Reporting\Transaction\GeneralAllocationReportController::class, 'index'])->name('general-allocation.index');
            Route::get('/general-allocation/export', [\App\Http\Controllers\Reporting\Transaction\GeneralAllocationReportController::class, 'export'])->name('general-allocation.export');
            
            // Panen Allocation Report
            Route::get('/panen-allocation',        [\App\Http\Controllers\Reporting\Transaction\PanenAllocationReportController::class, 'index'])->name('panen-allocation.index');
            Route::get('/panen-allocation/export', [\App\Http\Controllers\Reporting\Transaction\PanenAllocationReportController::class, 'export'])->name('panen-allocation.export');
            
            // Platform Checking Report
            Route::get('/platform-checking',        [\App\Http\Controllers\Reporting\Transaction\PlatformCheckingReportController::class, 'index'])->name('platform-checking.index');
            Route::get('/platform-checking/export', [\App\Http\Controllers\Reporting\Transaction\PlatformCheckingReportController::class, 'export'])->name('platform-checking.export');
            
            // Infield Grading Report
            Route::get('/infield-grading',        [\App\Http\Controllers\Reporting\Transaction\InfieldGradingReportController::class, 'index'])->name('infield-grading.index');
            Route::get('/infield-grading/export', [\App\Http\Controllers\Reporting\Transaction\InfieldGradingReportController::class, 'export'])->name('infield-grading.export');
            
            // Mill Bunch Audit Report
            Route::get('/mill-bunch-audit',        [\App\Http\Controllers\Reporting\Transaction\MillBunchAuditReportController::class, 'index'])->name('mill-bunch-audit.index');
            Route::get('/mill-bunch-audit/export', [\App\Http\Controllers\Reporting\Transaction\MillBunchAuditReportController::class, 'export'])->name('mill-bunch-audit.export');
            
            // Production Detail Report
            Route::get('/production-detail',        [\App\Http\Controllers\Reporting\Transaction\ProductionDetailReportController::class, 'index'])->name('production-detail.index');
            Route::get('/production-detail/export', [\App\Http\Controllers\Reporting\Transaction\ProductionDetailReportController::class, 'export'])->name('production-detail.export');
        });
        
        // Harvester Report
        Route::get('/harvester',        [\App\Http\Controllers\Reporting\HarvesterReportController::class, 'index'])->name('harvester.index');
        Route::get('/harvester/export', [\App\Http\Controllers\Reporting\HarvesterReportController::class, 'export'])->name('harvester.export');
        
        // Task Harvester Report
        Route::get('/task-harvester',        [\App\Http\Controllers\Reporting\TaskHarvesterReportController::class, 'index'])->name('task-harvester.index');
        Route::get('/task-harvester/export', [\App\Http\Controllers\Reporting\TaskHarvesterReportController::class, 'export'])->name('task-harvester.export');
        
        // Loader Report
        Route::get('/loader',        [\App\Http\Controllers\Reporting\LoaderReportController::class, 'index'])->name('loader.index');
        Route::get('/loader/export', [\App\Http\Controllers\Reporting\LoaderReportController::class, 'export'])->name('loader.export');
        
        // Supervisor Report
        Route::get('/supervisor',        [\App\Http\Controllers\Reporting\SupervisorReportController::class, 'index'])->name('supervisor.index');
        Route::get('/supervisor/export', [\App\Http\Controllers\Reporting\SupervisorReportController::class, 'export'])->name('supervisor.export');
        
        // Card Report
        Route::get('/card',        [\App\Http\Controllers\Reporting\CardReportController::class, 'index'])->name('card.index');
        Route::get('/card/export', [\App\Http\Controllers\Reporting\CardReportController::class, 'export'])->name('card.export');
        
        // Device Report
        Route::get('/device',        [\App\Http\Controllers\Reporting\DeviceReportController::class, 'index'])->name('device.index');
        Route::get('/device/export', [\App\Http\Controllers\Reporting\DeviceReportController::class, 'export'])->name('device.export');
        
        // Task Result Report
        Route::get('/task-result',        [\App\Http\Controllers\Reporting\TaskResultReportController::class, 'index'])->name('task-result.index');
        Route::get('/task-result/export', [\App\Http\Controllers\Reporting\TaskResultReportController::class, 'export'])->name('task-result.export');
        
        // Muster Chit Report
        Route::get('/muster-chit',        [\App\Http\Controllers\Reporting\MusterChitController::class, 'index'])->name('muster-chit.index');
        Route::get('/muster-chit/export', [\App\Http\Controllers\Reporting\MusterChitController::class, 'export'])->name('muster-chit.export');
        
    });

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

        // Substitution (Manager Substitution)
        Route::prefix('substitution')->name('substitution.')->group(function () {
            Route::get('/',            [SubstitutionController::class, 'index'])->name('index');
            Route::get('/create',      [SubstitutionController::class, 'create'])->name('create');
            Route::post('/',           [SubstitutionController::class, 'store'])->name('store');
            Route::get('/{id}/edit',   [SubstitutionController::class, 'edit'])->name('edit');
            Route::put('/{id}',        [SubstitutionController::class, 'update'])->name('update');
            Route::delete('/{id}',     [SubstitutionController::class, 'destroy'])->name('destroy');
        });

        // Master Data Substitution
        Route::prefix('master-data-substitution')->name('master-data-substitution.')->group(function () {
            Route::get('/',            [MasterDataSubstitutionController::class, 'index'])->name('index');
            Route::get('/create',      [MasterDataSubstitutionController::class, 'create'])->name('create');
            Route::post('/',           [MasterDataSubstitutionController::class, 'store'])->name('store');
            Route::get('/{id}/edit',   [MasterDataSubstitutionController::class, 'edit'])->name('edit');
            Route::put('/{id}',        [MasterDataSubstitutionController::class, 'update'])->name('update');
            Route::delete('/{id}',     [MasterDataSubstitutionController::class, 'destroy'])->name('destroy');
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

    // ── Transaction Entry (CI3 role 4 = Estate Staff family + managers) ─────
    //    Operational manual entry / correction (mobile captures GPS/photo/QR).
    Route::middleware(['roles:company_admin,admin,estate_manager,asst_manager,estate_staff,staff,pc,cs', 'company.scope'])
        ->prefix('transactions')->name('transactions.')->group(function () {
            $txEntry = function (string $uri, string $name, string $controller) {
                Route::prefix($uri)->name($name.'.')->group(function () use ($controller) {
                    Route::get('/',          [$controller, 'index'])->name('index');
                    Route::get('/datatable', [$controller, 'getDatatable'])->name('datatable');
                    Route::get('/create',    [$controller, 'create'])->name('create');
                    Route::post('/',         [$controller, 'store'])->name('store');
                    Route::get('/{id}/edit', [$controller, 'edit'])->name('edit');
                    Route::put('/{id}',      [$controller, 'update'])->name('update');
                    Route::delete('/{id}',   [$controller, 'destroy'])->name('destroy');
                });
            };
            $txEntry('attendance',                 'attendance',                 AttendanceEntryController::class);
            $txEntry('workdone',                   'workdone',                   WorkdoneEntryController::class);
            $txEntry('harvester-assignment',       'harvester_assignment',       HarvesterAssignmentController::class);
            $txEntry('general-worker-assignment',  'general_worker_assignment',  GeneralWorkerAssignmentController::class);
            $txEntry('overtime',                   'overtime',                   OvertimeEntryController::class);
            // Overtime AJAX
            Route::get('overtime/activity-details', [OvertimeEntryController::class, 'activityDetails'])->name('transactions.overtime.activity-details');

            $txEntry('vra',                        'vra',                        VraEntryController::class);
            // VRA AJAX — defined outside txEntry to get correct names
            Route::get('vra/meas-points',  [VraEntryController::class, 'measPoints'])->name('transactions.vra.meas-points');
            Route::get('vra/by-type',      [VraEntryController::class, 'vraByType'])->name('transactions.vra.by-type');

            // Platform Checking — master-detail (header + detail items)
            Route::prefix('platform-checking')->name('platform_checking.')->group(function () {
                Route::get('/',          [PlatformCheckingController::class, 'index'])->name('index');
                Route::get('/datatable', [PlatformCheckingController::class, 'getDatatable'])->name('datatable');
                Route::get('/create',    [PlatformCheckingController::class, 'create'])->name('create');
                Route::post('/',         [PlatformCheckingController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [PlatformCheckingController::class, 'edit'])->name('edit');
                Route::put('/{id}',      [PlatformCheckingController::class, 'update'])->name('update');
                Route::delete('/{id}',   [PlatformCheckingController::class, 'destroy'])->name('destroy');
            });
            // OPH — CRUD + CSV import (flat). CSV static routes before {id}.
            Route::prefix('oph')->name('oph.')->group(function () {
                Route::get('/',                    [OphEntryController::class, 'index'])->name('index');
                Route::get('/datatable',           [OphEntryController::class, 'getDatatable'])->name('datatable');
                Route::get('/create',              [OphEntryController::class, 'create'])->name('create');
                Route::get('/upload',              [OphEntryController::class, 'upload'])->name('upload');
                Route::post('/preview',            [OphEntryController::class, 'preview'])->name('preview');
                Route::post('/save-uploaded-data', [OphEntryController::class, 'saveUploadedData'])->name('save-uploaded-data');
                Route::get('/cancel',              [OphEntryController::class, 'cancelUpload'])->name('cancel');
                Route::get('/generate-csv',        [OphEntryController::class, 'generateCsv'])->name('generate-csv');
                Route::post('/',                   [OphEntryController::class, 'store'])->name('store');
                Route::get('/{id}/edit',           [OphEntryController::class, 'edit'])->name('edit');
                Route::put('/{id}',                [OphEntryController::class, 'update'])->name('update');
                Route::delete('/{id}',             [OphEntryController::class, 'destroy'])->name('destroy');
            });

            // OPH Mill Grader — read-only monitoring (no CRUD).
            Route::prefix('oph-mill-grader')->name('oph_mill_grader.')->group(function () {
                Route::get('/',          [OphMillGraderController::class, 'index'])->name('index');
                Route::get('/datatable', [OphMillGraderController::class, 'getDatatable'])->name('datatable');
                Route::get('/{id}',      [OphMillGraderController::class, 'detail'])->name('detail');
            });

            // Checkpoint CP1 / CP2 — master-detail (header + OPH lines + loaders).
            $cpEntry = function (string $uri, string $name, string $controller) {
                Route::prefix($uri)->name($name.'.')->group(function () use ($controller) {
                    Route::get('/',                    [$controller, 'index'])->name('index');
                    Route::get('/datatable',           [$controller, 'getDatatable'])->name('datatable');
                    Route::get('/create',              [$controller, 'create'])->name('create');
                    Route::get('/available-oph',       [$controller, 'availableOph'])->name('available-oph');
                    Route::get('/upload',              [$controller, 'upload'])->name('upload');
                    Route::post('/preview',            [$controller, 'preview'])->name('preview');
                    Route::post('/save-uploaded-data', [$controller, 'saveUploadedData'])->name('save-uploaded-data');
                    Route::get('/cancel',              [$controller, 'cancelUpload'])->name('cancel');
                    Route::get('/generate-csv',        [$controller, 'generateCsv'])->name('generate-csv');
                    Route::post('/',                   [$controller, 'store'])->name('store');
                    Route::get('/{id}/edit',           [$controller, 'edit'])->name('edit');
                    Route::put('/{id}',                [$controller, 'update'])->name('update');
                    Route::delete('/{id}',             [$controller, 'destroy'])->name('destroy');
                });
            };
            $cpEntry('checkpoint-1', 'checkpoint_1', Checkpoint1Controller::class);
            $cpEntry('checkpoint-2', 'checkpoint_2', Checkpoint2Controller::class);

            // FDN / Delivery Note — master-detail (header + OPH lines + loaders).
            Route::prefix('delivery-note')->name('delivery_note.')->group(function () {
                Route::get('/',                    [FdnController::class, 'index'])->name('index');
                Route::get('/datatable',           [FdnController::class, 'getDatatable'])->name('datatable');
                Route::get('/create',              [FdnController::class, 'create'])->name('create');
                Route::get('/available-oph',       [FdnController::class, 'availableOph'])->name('available-oph');
                Route::get('/upload',              [FdnController::class, 'upload'])->name('upload');
                Route::post('/preview',            [FdnController::class, 'preview'])->name('preview');
                Route::post('/save-uploaded-data', [FdnController::class, 'saveUploadedData'])->name('save-uploaded-data');
                Route::get('/cancel',              [FdnController::class, 'cancelUpload'])->name('cancel');
                Route::get('/generate-csv',        [FdnController::class, 'generateCsv'])->name('generate-csv');
                Route::post('/',                   [FdnController::class, 'store'])->name('store');
                Route::get('/{id}/edit',           [FdnController::class, 'edit'])->name('edit');
                Route::put('/{id}',                [FdnController::class, 'update'])->name('update');
                Route::delete('/{id}',             [FdnController::class, 'destroy'])->name('destroy');
            });

            // ── Coconut: Harvesting Chit + FDN (master-detail) ─────────────────
            Route::prefix('harvesting-chit-coconut')->name('harvesting_chit_coconut.')->group(function () {
                Route::get('/',                    [CoconutHarvestingChitController::class, 'index'])->name('index');
                Route::get('/datatable',           [CoconutHarvestingChitController::class, 'getDatatable'])->name('datatable');
                Route::get('/create',              [CoconutHarvestingChitController::class, 'create'])->name('create');
                Route::get('/upload',              [CoconutHarvestingChitController::class, 'upload'])->name('upload');
                Route::post('/preview',            [CoconutHarvestingChitController::class, 'preview'])->name('preview');
                Route::post('/save-uploaded-data', [CoconutHarvestingChitController::class, 'saveUploadedData'])->name('save-uploaded-data');
                Route::get('/cancel',              [CoconutHarvestingChitController::class, 'cancelUpload'])->name('cancel');
                Route::get('/generate-csv',        [CoconutHarvestingChitController::class, 'generateCsv'])->name('generate-csv');
                Route::post('/',                   [CoconutHarvestingChitController::class, 'store'])->name('store');
                Route::get('/{id}/edit',           [CoconutHarvestingChitController::class, 'edit'])->name('edit');
                Route::put('/{id}',                [CoconutHarvestingChitController::class, 'update'])->name('update');
                Route::delete('/{id}',             [CoconutHarvestingChitController::class, 'destroy'])->name('destroy');
            });
            Route::prefix('delivery-note-coconut')->name('delivery_note_coconut.')->group(function () {
                Route::get('/',                    [CoconutFdnController::class, 'index'])->name('index');
                Route::get('/datatable',           [CoconutFdnController::class, 'getDatatable'])->name('datatable');
                Route::get('/create',              [CoconutFdnController::class, 'create'])->name('create');
                Route::get('/available-chit',      [CoconutFdnController::class, 'availableChit'])->name('available-chit');
                Route::get('/upload',              [CoconutFdnController::class, 'upload'])->name('upload');
                Route::post('/preview',            [CoconutFdnController::class, 'preview'])->name('preview');
                Route::post('/save-uploaded-data', [CoconutFdnController::class, 'saveUploadedData'])->name('save-uploaded-data');
                Route::get('/cancel',              [CoconutFdnController::class, 'cancelUpload'])->name('cancel');
                Route::get('/generate-csv',        [CoconutFdnController::class, 'generateCsv'])->name('generate-csv');
                Route::post('/',                   [CoconutFdnController::class, 'store'])->name('store');
                Route::get('/{id}/edit',           [CoconutFdnController::class, 'edit'])->name('edit');
                Route::put('/{id}',                [CoconutFdnController::class, 'update'])->name('update');
                Route::delete('/{id}',             [CoconutFdnController::class, 'destroy'])->name('destroy');
            });

            // Coconut: CP (Checkpoint) — master-detail (header + chit lines + loaders).
            Route::prefix('checkpoint-coconut')->name('checkpoint_coconut.')->group(function () {
                Route::get('/',              [CheckpointCoconutController::class, 'index'])->name('index');
                Route::get('/datatable',     [CheckpointCoconutController::class, 'getDatatable'])->name('datatable');
                Route::get('/create',        [CheckpointCoconutController::class, 'create'])->name('create');
                Route::get('/available-chit',[CheckpointCoconutController::class, 'availableChit'])->name('available-chit');
                Route::post('/',             [CheckpointCoconutController::class, 'store'])->name('store');
                Route::get('/{id}/edit',     [CheckpointCoconutController::class, 'edit'])->name('edit');
                Route::put('/{id}',          [CheckpointCoconutController::class, 'update'])->name('update');
                Route::delete('/{id}',       [CheckpointCoconutController::class, 'destroy'])->name('destroy');
            });

            // Coconut: Grading — edit grading material lines of a chit already in a CP.
            Route::prefix('grading-coconut')->name('grading_coconut.')->group(function () {
                Route::get('/',          [GradingCoconutController::class, 'index'])->name('index');
                Route::get('/datatable', [GradingCoconutController::class, 'getDatatable'])->name('datatable');
                Route::get('/{id}/edit', [GradingCoconutController::class, 'edit'])->name('edit');
                Route::put('/{id}',      [GradingCoconutController::class, 'update'])->name('update');
            });
        });

    // ── Reporting routes ───────────────────────────────────────────────────
    Route::prefix('reporting')->name('reporting.')->group(function () {
        // TODO Sprint 7+
    });

    // ── Closing SAP ─────────────────────────────────────────────────────────
    Route::prefix('closing')->name('closing.')->middleware(['roles:company_admin,admin,estate_manager,asst_manager,estate_staff,staff,pc,cs,it_staff'])->group(function () {
        // Helper closure: standard closing routes (index, closing, lock, relock).
        $closingEntry = function (string $prefix, string $name, string $ctrl) {
            Route::prefix($prefix)->name($name . '.')->group(function () use ($ctrl) {
                Route::get('/',        [$ctrl, 'index'])->name('index');
                Route::post('/close',  [$ctrl, 'closing'])->name('closing');
                Route::post('/lock',   [$ctrl, 'lock'])->name('lock');
                Route::post('/relock', [$ctrl, 'relock'])->name('relock');
            });
        };

        $closingEntry('attendance',    'attendance',     \App\Http\Controllers\Closing\ClosingAttendanceController::class);
        $closingEntry('oph',           'oph',            \App\Http\Controllers\Closing\ClosingOphController::class);
        $closingEntry('workdone',      'workdone',       \App\Http\Controllers\Closing\ClosingWorkdoneController::class);
        $closingEntry('cp',            'cp',             \App\Http\Controllers\Closing\ClosingCpController::class);
        $closingEntry('fdn',           'fdn',            \App\Http\Controllers\Closing\ClosingFdnController::class);
        $closingEntry('overtime',      'overtime',       \App\Http\Controllers\Closing\ClosingOvertimeController::class);
        $closingEntry('vra',           'vra',            \App\Http\Controllers\Closing\ClosingVraController::class);
        $closingEntry('coconut-chit',  'coconut_chit',   \App\Http\Controllers\Closing\ClosingCoconutChitController::class);
        $closingEntry('coconut-fdn',   'coconut_fdn',    \App\Http\Controllers\Closing\ClosingCoconutFdnController::class);

        // Adjustment log (read-only)
        Route::prefix('adjustment')->name('adjustment.')->group(function () {
            Route::get('/',          [\App\Http\Controllers\Closing\AdjustmentController::class, 'index'])->name('index');
            Route::get('/datatable', [\App\Http\Controllers\Closing\AdjustmentController::class, 'getDatatable'])->name('datatable');
        });
    });

    // ── SAP Closing Approval (Estate Manager) ───────────────────────────────
    Route::prefix('sap-approval')->name('sap-approval.')->middleware(['roles:estate_manager,admin,company_admin'])->group(function () {
        $approvalEntry = function (string $prefix, string $name, string $ctrl) {
            Route::prefix($prefix)->name($name . '.')->group(function () use ($ctrl) {
                Route::get('/',             [$ctrl, 'index'])->name('index');
                Route::post('/save-approval', [$ctrl, 'saveApproval'])->name('save-approval');
            });
        };

        $approvalEntry('attendance', 'attendance', \App\Http\Controllers\SapApproval\SapApprovalAttendanceController::class);
        $approvalEntry('workdone',   'workdone',   \App\Http\Controllers\SapApproval\SapApprovalWorkdoneController::class);
        $approvalEntry('oph',        'oph',        \App\Http\Controllers\SapApproval\SapApprovalOphController::class);
        $approvalEntry('overtime',   'overtime',   \App\Http\Controllers\SapApproval\SapApprovalOvertimeController::class);
    });

    // ── Planning (Estate Manager) ────────────────────────────────────────────
    Route::prefix('planning')->name('planning.')->middleware(['roles:estate_manager,admin,company_admin'])->group(function () {
        Route::prefix('workplan')->name('workplan.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Planning\PlanningWorkplanController::class, 'index'])->name('index');
            Route::get('/detail', [\App\Http\Controllers\Planning\PlanningWorkplanController::class, 'detail'])->name('detail');
            Route::post('/submit', [\App\Http\Controllers\Planning\PlanningWorkplanController::class, 'submitForApproval'])->name('submit');
        });
        Route::prefix('harvesting-plan')->name('harvesting_plan.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Planning\PlanningHarvestingPlanController::class, 'index'])->name('index');
            Route::get('/detail', [\App\Http\Controllers\Planning\PlanningHarvestingPlanController::class, 'detail'])->name('detail');
            Route::post('/submit', [\App\Http\Controllers\Planning\PlanningHarvestingPlanController::class, 'submitForApproval'])->name('submit');
        });
        Route::prefix('harvesting-plan-coconut')->name('harvesting_plan_coconut.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Planning\PlanningHarvestingPlanCoconutController::class, 'index'])->name('index');
            Route::get('/detail', [\App\Http\Controllers\Planning\PlanningHarvestingPlanCoconutController::class, 'detail'])->name('detail');
            Route::post('/submit', [\App\Http\Controllers\Planning\PlanningHarvestingPlanCoconutController::class, 'submitForApproval'])->name('submit');
        });
        Route::prefix('gi-plan')->name('gi_plan.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Planning\PlanningGiPlanController::class, 'index'])->name('index');
            Route::get('/detail', [\App\Http\Controllers\Planning\PlanningGiPlanController::class, 'detail'])->name('detail');
            Route::post('/submit', [\App\Http\Controllers\Planning\PlanningGiPlanController::class, 'submitForApproval'])->name('submit');
        });
    });

    // -- Approval (Assistant Manager) -----------------------------------------
    Route::prefix('approval')->name('approval.')->middleware(['roles:asst_manager,admin,company_admin'])->group(function () {
        Route::prefix('workplan')->name('workplan.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Approval\ApprovalWorkplanController::class, 'index'])->name('index');
            Route::get('/detail', [\App\Http\Controllers\Approval\ApprovalWorkplanController::class, 'detail'])->name('detail');
            Route::post('/approve', [\App\Http\Controllers\Approval\ApprovalWorkplanController::class, 'approve'])->name('approve');
        });
        Route::prefix('harvesting-plan')->name('harvesting_plan.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Approval\ApprovalHarvestingPlanController::class, 'index'])->name('index');
            Route::get('/detail', [\App\Http\Controllers\Approval\ApprovalHarvestingPlanController::class, 'detail'])->name('detail');
            Route::post('/approve', [\App\Http\Controllers\Approval\ApprovalHarvestingPlanController::class, 'approve'])->name('approve');
        });
        Route::prefix('unplanned-activity')->name('unplanned_activity.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Approval\ApprovalUnplannedActivityController::class, 'index'])->name('index');
            Route::post('/approve', [\App\Http\Controllers\Approval\ApprovalUnplannedActivityController::class, 'approve'])->name('approve');
        });
        Route::prefix('oph')->name('oph.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Approval\ApprovalOphController::class, 'index'])->name('index');
            Route::post('/approve', [\App\Http\Controllers\Approval\ApprovalOphController::class, 'approve'])->name('approve');
        });
        Route::prefix('overtime')->name('overtime.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Approval\ApprovalOvertimeController::class, 'index'])->name('index');
            Route::post('/approve', [\App\Http\Controllers\Approval\ApprovalOvertimeController::class, 'approve'])->name('approve');
        });
        Route::prefix('harvesting-chit-coconut')->name('harvesting_chit_coconut.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Approval\ApprovalHarvestingChitCoconutController::class, 'index'])->name('index');
            Route::post('/approve', [\App\Http\Controllers\Approval\ApprovalHarvestingChitCoconutController::class, 'approve'])->name('approve');
        });
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
