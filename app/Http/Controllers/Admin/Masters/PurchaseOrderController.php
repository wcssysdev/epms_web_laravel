<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\PurchaseOrder::class; }
    protected function tableName(): string    { return 'm_purchase_order'; }
    protected function resourceName(): string { return 'Purchase Order'; }
    protected function viewPrefix(): string   { return 'admin.masters.purchase_order'; }
    protected function routePrefix(): string  { return 'masters.purchase_order'; }

    protected function datatableColumns(): array
    {
        return [
            'po_number'        => 'PO Number',
            'po_type'          => 'Type',
            'vendor_name'      => 'Supplier',
            'material_code'    => 'Material',
            'material_name'    => 'Material Name',
            'qty_order'        => 'Qty',
            'uom'              => 'UOM',
            'plant_code'       => 'Plant',
        ];
    }

    protected function csvHeaders(): array
    {
        return [
            'po_number', 'po_type', 'vendor_code', 'vendor_name', 'plant_code', 'organization',
            'po_group', 'material_line_num', 'material_code', 'material_name', 'material_group',
            'qty_order', 'uom', 'currency_key', 'net_price', 'unit_price', 'order_price_unit', 'net_value',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $no = trim($row['po_number'] ?? '');
        if (empty($no)) return null;
        return [
            'po_number'         => strtoupper($no),
            'po_type'           => trim($row['po_type'] ?? ''),
            'vendor_code'       => trim($row['vendor_code'] ?? ''),
            'vendor_name'       => trim($row['vendor_name'] ?? ''),
            'plant_code'        => trim($row['plant_code'] ?? ''),
            'organization'      => trim($row['organization'] ?? ''),
            'po_group'          => trim($row['po_group'] ?? ''),
            'material_line_num' => trim($row['material_line_num'] ?? ''),
            'material_code'     => trim($row['material_code'] ?? ''),
            'material_name'     => trim($row['material_name'] ?? ''),
            'material_group'    => trim($row['material_group'] ?? ''),
            'qty_order'         => is_numeric($row['qty_order'] ?? '') ? (float) $row['qty_order'] : null,
            'uom'               => trim($row['uom'] ?? ''),
            'currency_key'      => trim($row['currency_key'] ?? ''),
            'net_price'         => is_numeric($row['net_price'] ?? '') ? (float) $row['net_price'] : null,
            'unit_price'        => is_numeric($row['unit_price'] ?? '') ? (float) $row['unit_price'] : null,
            'order_price_unit'  => trim($row['order_price_unit'] ?? ''),
            'net_value'         => is_numeric($row['net_value'] ?? '') ? (float) $row['net_value'] : null,
            'is_deleted'        => false,
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['po_number'])) return 'PO Number is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_PO_OUT',
            'urn'     => 'ZEPMS_PO_OUT',
            'filters' => ['WERKS' => '{plant_code}'],
            'columns' => ['EBELN', 'BSART', 'BEDAT', 'WERKS', 'EKORG', 'EKGRP', 'LIFNR', 'NAME1',
                          'EBELP', 'MATNR', 'TXZ01', 'MATKL', 'MENGE', 'MEINS', 'WAERS', 'NETPR',
                          'PEINH', 'BPRME', 'NETWR'],
            'mapping' => [
                'po_number'         => 'EBELN',
                'po_type'           => 'BSART',
                'plant_code'        => 'WERKS',
                'organization'      => 'EKORG',
                'po_group'          => 'EKGRP',
                'supplier_account'  => 'LIFNR',
                'vendor_code'       => 'LIFNR',
                'vendor_name'       => 'NAME1',
                'material_line_num' => 'EBELP',
                'material_code'     => 'MATNR',
                'material_name'     => 'TXZ01',
                'material_group'    => 'MATKL',
                'uom'               => 'MEINS',
                'currency_key'      => 'WAERS',
                'order_price_unit'  => 'BPRME',
                // numeric + date handled in transformSapRow
            ],
        ];
    }

    protected function transformSapRow(array $master, array $staging): array
    {
        $master['qty_order']        = is_numeric($staging['MENGE'] ?? '') ? (float) $staging['MENGE'] : null;
        $master['net_price']        = is_numeric($staging['NETPR'] ?? '') ? (float) $staging['NETPR'] : null;
        $master['unit_price']       = is_numeric($staging['PEINH'] ?? '') ? (float) $staging['PEINH'] : null;
        $master['net_value']        = is_numeric($staging['NETWR'] ?? '') ? (float) $staging['NETWR'] : null;
        $master['sap_created_date'] = $this->parseDate($staging['BEDAT'] ?? '');
        $master['is_deleted']       = false;
        return $master;
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_purchase_order')->where('company_id', $this->companyId())
            ->orderBy('po_number')->distinct()->get(['po_number', 'vendor_name']);
        return $this->jsonSuccess('OK', $rows);
    }

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') return null;
        $val = str_replace('.', '-', $val);
        try { return \Carbon\Carbon::parse($val)->toDateString(); } catch (\Exception $e) { return null; }
    }
}
