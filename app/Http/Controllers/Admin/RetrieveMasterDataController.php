<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class RetrieveMasterDataController extends BaseController
{
    /**
     * Master data tables + their SAP params for sync.
     * Each entry: [table, label, sap_param, controller_class]
     */
    private function masterDataMap(): array
    {
        return [
            'estate'       => ['label' => 'Estate',       'table' => 'm_estate',       'icon' => '🏠'],
            'division'     => ['label' => 'Division',     'table' => 'm_division',      'icon' => '📍'],
            'block'        => ['label' => 'Block',        'table' => 'm_block',         'icon' => '📦'],
            'employee'     => ['label' => 'Employee',     'table' => 'm_employee',      'icon' => '👤'],
            'activity'     => ['label' => 'Activity',     'table' => 'm_activity',      'icon' => '📋'],
            'material'     => ['label' => 'Material',     'table' => 'm_material',      'icon' => '🧰'],
            'vendor'       => ['label' => 'Vendor',       'table' => 'm_vendor',        'icon' => '🏭'],
            'cost_center'  => ['label' => 'Cost Center',  'table' => 'm_cost_center',   'icon' => '💰'],
            'work_center'  => ['label' => 'Work Center',  'table' => 'm_work_center',   'icon' => '🔧'],
            'worktype'     => ['label' => 'Worktype',     'table' => 'm_worktype',      'icon' => '📂'],
        ];
    }

    public function index(): View
    {
        $map    = $this->masterDataMap();
        $status = [];

        foreach ($map as $key => $item) {
            $log = DB::table('master_data_log')
                ->where('company_id', $this->companyId())
                ->where('table_name', $item['table'])
                ->orderByDesc('last_updated_at')
                ->first();

            $status[$key] = [
                'label'        => $item['label'],
                'table'        => $item['table'],
                'icon'         => $item['icon'],
                'total'        => DB::table($item['table'])->where('company_id', $this->companyId())->count(),
                'last_updated' => $log?->last_updated_at,
                'last_refresh' => $log?->last_refresh_at,
            ];
        }

        return view('admin.retrieve_master_data.index', compact('status'));
    }

    // Trigger sync for a single table
    public function sync(Request $request): JsonResponse
    {
        $request->validate(['table_key' => 'required|string']);
        $key = $request->table_key;
        $map = $this->masterDataMap();

        if (!isset($map[$key])) {
            return $this->jsonError("Unknown master data: {$key}");
        }

        $item   = $map[$key];
        $config = $this->companyConfig;

        if (!$config?->sap_api_url) {
            return $this->jsonError('SAP API URL is not configured. Please update Estate Settings.');
        }

        try {
            // Trigger refresh via master data log timestamp reset
            DB::table('master_data_log')->updateOrInsert(
                ['company_id' => $this->companyId(), 'table_name' => $item['table']],
                ['last_refresh_at' => now(), 'last_updated_by' => $this->userId(), 'is_replaced' => false]
            );

            AuditService::log(
                AuditService::TYPE_SYSTEM,
                AuditService::ACTION_UPDATE,
                "Triggered SAP sync for {$item['label']} by {$this->userName()}"
            );

            return $this->jsonSuccess("Sync triggered for {$item['label']}. Data will be refreshed.", [
                'table'       => $item['table'],
                'total'       => DB::table($item['table'])->where('company_id', $this->companyId())->count(),
                'last_refresh'=> now()->format('d/m/Y H:i:s'),
            ]);

        } catch (\Exception $e) {
            return $this->jsonError("Sync failed: " . $e->getMessage());
        }
    }

    // Sync ALL tables
    public function syncAll(): JsonResponse
    {
        $map     = $this->masterDataMap();
        $results = [];
        $errors  = 0;

        foreach ($map as $key => $item) {
            try {
                DB::table('master_data_log')->updateOrInsert(
                    ['company_id' => $this->companyId(), 'table_name' => $item['table']],
                    ['last_refresh_at' => now(), 'last_updated_by' => $this->userId(), 'is_replaced' => false]
                );
                $results[$key] = ['success' => true, 'label' => $item['label']];
            } catch (\Exception $e) {
                $results[$key] = ['success' => false, 'label' => $item['label'], 'error' => $e->getMessage()];
                $errors++;
            }
        }

        AuditService::log(AuditService::TYPE_SYSTEM, AuditService::ACTION_UPDATE, "Triggered sync ALL master data by {$this->userName()}");

        return $this->jsonSuccess(
            $errors === 0 ? "All " . count($map) . " master data sync triggered." : "{$errors} sync(s) failed.",
            $results
        );
    }
}
