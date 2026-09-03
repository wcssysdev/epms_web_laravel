<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Yajra\DataTables\Facades\DataTables;

class MaterialController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Material::class; }
    protected function tableName(): string    { return 'm_material'; }
    protected function resourceName(): string { return 'Material'; }
    protected function viewPrefix(): string   { return 'admin.masters.material'; }
    protected function routePrefix(): string  { return 'masters.material'; }

    protected function datatableColumns(): array
    {
        return [
            'material_code'  => 'Code',
            'material_name'  => 'Name',
            'material_uom'   => 'UOM',
            'material_type'  => 'Type',
            'plant_code'     => 'Plant',
            'updated_at'     => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['material_code', 'material_name', 'material_uom', 'plant_code', 'sloc_code', 'material_batch', 'material_group', 'material_type'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['material_code'] ?? '');
        if (empty($code)) return null;
        return [
            'material_code'  => strtoupper($code),
            'material_name'  => trim($row['material_name'] ?? ''),
            'material_uom'   => trim($row['material_uom'] ?? ''),
            'plant_code'     => trim($row['plant_code'] ?? ''),
            'sloc_code'      => trim($row['sloc_code'] ?? ''),
            'material_batch' => trim($row['material_batch'] ?? ''),
            'material_group' => trim($row['material_group'] ?? ''),
            'material_type'  => trim($row['material_type'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['material_code'])) return 'Material Code is required.';
        if (empty($row['material_name'])) return 'Material Name is required.';
        return null;
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('material_code');
        if ($request->filled('material_type')) $query->where('material_type', $request->material_type);
        if ($request->filled('plant_code'))    $query->where('plant_code', $request->plant_code);
        return DataTables::query($query)->addIndexColumn()->make(true);
    }

    public function generateQr(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate(['material_codes' => 'required|array|min:1|max:50']);
        $qrData = [];
        foreach ($request->material_codes as $code) {
            $mat = DB::table('m_material')->where('company_id', $this->companyId())->where('material_code', $code)->first();
            if (!$mat) continue;
            $content = json_encode(['type' => 'MATERIAL', 'material_code' => $mat->material_code, 'material_name' => $mat->material_name, 'uom' => $mat->material_uom]);
            $qrData[] = ['material_code' => $mat->material_code, 'material_name' => $mat->material_name, 'qr_svg' => base64_encode(QrCode::format('svg')->size(200)->generate($content))];
        }
        return $this->jsonSuccess('QR codes generated.', $qrData);
    }

    public function lookup(Request $request): JsonResponse
    {
        $query = DB::table('m_material')->where('company_id', $this->companyId())->orderBy('material_name');
        if ($request->filled('material_type')) $query->where('material_type', $request->material_type);
        if ($request->filled('search')) $query->where(fn($q) => $q->where('material_code', 'ilike', "%{$request->search}%")->orWhere('material_name', 'ilike', "%{$request->search}%"));
        return $this->jsonSuccess('OK', $query->limit(50)->get(['material_code', 'material_name', 'material_uom', 'material_type']));
    }

    // ── SAP two-step config ───────────────────────────────────────────────────
    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EM_MATERIAL_OUT',
            'urn'     => 'ZEPMS_EM_MATERIAL_OUT',
            'filters' => ['MATKL' => '*', 'WERKS' => '{plant_code}'],
            'columns' => ['MATNR', 'MAKTX', 'WERKS', 'MEINS', 'MTART', 'MATKL', 'LGORT', 'CHARG'],
            'mapping' => [
                'material_code'  => 'MATNR',
                'material_name'  => 'MAKTX',
                'plant_code'     => 'WERKS',
                'material_uom'   => 'MEINS',
                'material_type'  => 'MTART',
                'material_group' => 'MATKL',
                'sloc_code'      => 'LGORT',
                'material_batch' => 'CHARG',
            ],
        ];
    }
}
