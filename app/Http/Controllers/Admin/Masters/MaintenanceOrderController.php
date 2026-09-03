<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MaintenanceOrderController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\MaintenanceOrder::class; }
    protected function tableName(): string    { return 'm_maintenance_order'; }
    protected function resourceName(): string { return 'Maintenance Order'; }
    protected function viewPrefix(): string   { return 'admin.masters.maint_order'; }
    protected function routePrefix(): string  { return 'masters.maint_order'; }

    protected function datatableColumns(): array
    {
        return [
            'order_number'   => 'Order Number',
            'order_desc'     => 'Description',
            'sales_doc_type' => 'Doc Type',
            'business_area'  => 'Business Area',
            'plant_code'     => 'Plant',
            'updated_at'     => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['order_number', 'order_desc', 'sales_doc_type', 'business_area', 'plant_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['order_number'] ?? '');
        if (empty($code)) return null;
        return [
            'order_number'   => strtoupper($code),
            'order_desc'     => trim($row['order_desc'] ?? ''),
            'sales_doc_type' => trim($row['sales_doc_type'] ?? ''),
            'business_area'  => trim($row['business_area'] ?? ''),
            'plant_code'     => trim($row['plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['order_number'])) return 'Order Number is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_MAINT_ORDER_OUT',
            'urn'     => 'ZEPMS_MAINT_ORDER_OUT',
            'filters' => ['WERKS' => '{plant_code}'],
            'columns' => ['AUFNR', 'KTEXT', 'AUART', 'GSBER', 'BUKRS', 'WERKS'],
            'mapping' => [
                'order_number'   => 'AUFNR',
                'order_desc'     => 'KTEXT',
                'sales_doc_type' => 'AUART',
                'business_area'  => 'GSBER',
                'plant_code'     => 'WERKS',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_maintenance_order')->where('company_id', $this->companyId())
            ->orderBy('order_number')->get(['order_number', 'order_desc']);
        return $this->jsonSuccess('OK', $rows);
    }
}
