<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\SalesOrder::class; }
    protected function tableName(): string    { return 'm_sales_order'; }
    protected function resourceName(): string { return 'Sales Order'; }
    protected function viewPrefix(): string   { return 'admin.masters.sales_order'; }
    protected function routePrefix(): string  { return 'masters.sales_order'; }

    protected function datatableColumns(): array
    {
        return [
            'sales_order_no'  => 'Sales Order No',
            'item_no'         => 'Item',
            'customer_code'   => 'Customer',
            'material_code'   => 'Material',
            'item_qty'        => 'Qty',
            'item_uom'        => 'UOM',
            'sales_order_date'=> 'Order Date',
        ];
    }

    protected function csvHeaders(): array
    {
        return [
            'sales_order_no', 'item_no', 'customer_reference', 'customer_code', 'material_code',
            'item_qty', 'item_uom', 'payment_term', 'reason_for_rejection', 'item_description',
            'sales_order_type', 'sales_order_date', 'plant_code',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $no = trim($row['sales_order_no'] ?? '');
        if (empty($no)) return null;
        return [
            'sales_order_no'       => strtoupper($no),
            'item_no'              => trim($row['item_no'] ?? ''),
            'customer_reference'   => trim($row['customer_reference'] ?? ''),
            'customer_code'        => trim($row['customer_code'] ?? ''),
            'material_code'        => trim($row['material_code'] ?? ''),
            'item_qty'             => trim($row['item_qty'] ?? ''),
            'item_uom'             => trim($row['item_uom'] ?? ''),
            'payment_term'         => trim($row['payment_term'] ?? ''),
            'reason_for_rejection' => trim($row['reason_for_rejection'] ?? ''),
            'item_description'     => trim($row['item_description'] ?? ''),
            'sales_order_type'     => trim($row['sales_order_type'] ?? ''),
            'sales_order_date'     => trim($row['sales_order_date'] ?? ''),
            'plant_code'           => trim($row['plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['sales_order_no'])) return 'Sales Order No is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_SD_SORD_OUT',
            'urn'     => 'ZEPMS_SD_SORD_OUT',
            'filters' => ['P_WERKS' => '{plant_code}'],
            'columns' => ['WERKS', 'VBELN', 'POSNR', 'BSTNK', 'KUNNR', 'MATNR', 'KWMENG', 'VRKME',
                          'ZTERM', 'ABGRU', 'ARKTX', 'TYPE', 'ERDAT', 'ERNAM', 'AUDAT'],
            'mapping' => [
                'plant_code'           => 'WERKS',
                'sales_order_no'       => 'VBELN',
                'item_no'              => 'POSNR',
                'customer_reference'   => 'BSTNK',
                'customer_code'        => 'KUNNR',
                'material_code'        => 'MATNR',
                'item_qty'             => 'KWMENG',
                'item_uom'             => 'VRKME',
                'payment_term'         => 'ZTERM',
                'reason_for_rejection' => 'ABGRU',
                'item_description'     => 'ARKTX',
                'sales_order_type'     => 'TYPE',
                'sap_created_date'     => 'ERDAT',
                'sap_created_by'       => 'ERNAM',
                'sales_order_date'     => 'AUDAT',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_sales_order')->where('company_id', $this->companyId())
            ->orderBy('sales_order_no')->distinct()->get(['sales_order_no', 'customer_code']);
        return $this->jsonSuccess('OK', $rows);
    }
}
