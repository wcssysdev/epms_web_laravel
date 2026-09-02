<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VendorController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Vendor::class; }
    protected function tableName(): string    { return 'm_vendor'; }
    protected function resourceName(): string { return 'Vendor'; }
    protected function viewPrefix(): string   { return 'admin.masters.vendor'; }
    protected function routePrefix(): string  { return 'masters.vendor'; }

    protected function datatableColumns(): array
    {
        return [
            'vendor_code'  => 'Vendor Code',
            'vendor_name'  => 'Vendor Name',
            'plant_code'   => 'Plant Code',
            'updated_at'   => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['vendor_code', 'vendor_name', 'plant_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['vendor_code'] ?? '');
        if (empty($code)) return null;
        return [
            'vendor_code' => strtoupper($code),
            'vendor_name' => trim($row['vendor_name'] ?? ''),
            'plant_code'  => trim($row['plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['vendor_code'])) return 'Vendor Code is required.';
        if (empty($row['vendor_name'])) return 'Vendor Name is required.';
        return null;
    }

    public function generateQr(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate(['vendor_codes' => 'required|array|min:1|max:50']);
        $qrData = [];
        foreach ($request->vendor_codes as $code) {
            $vendor = DB::table('m_vendor')
                ->where('company_id', $this->companyId())
                ->where('vendor_code', $code)->first();
            if (!$vendor) continue;
            $content = json_encode(['type' => 'VENDOR', 'vendor_code' => $vendor->vendor_code, 'vendor_name' => $vendor->vendor_name]);
            $qrData[] = [
                'vendor_code' => $vendor->vendor_code,
                'vendor_name' => $vendor->vendor_name,
                'qr_svg'      => base64_encode(QrCode::format('svg')->size(200)->generate($content)),
            ];
        }
        return $this->jsonSuccess('QR codes generated.', $qrData);
    }

    public function lookup(): JsonResponse
    {
        $vendors = DB::table('m_vendor')->where('company_id', $this->companyId())
            ->orderBy('vendor_code')->get(['vendor_code', 'vendor_name']);
        return $this->jsonSuccess('OK', $vendors);
    }
}
