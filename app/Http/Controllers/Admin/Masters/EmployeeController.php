<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Models\Master\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeController extends BaseMasterController
{
    protected function modelClass(): string   { return Employee::class; }
    protected function tableName(): string    { return 'm_employee'; }
    protected function resourceName(): string { return 'Employee'; }
    protected function viewPrefix(): string   { return 'admin.masters.employee'; }
    protected function routePrefix(): string  { return 'masters.employee'; }

    protected function datatableColumns(): array
    {
        return [
            'employee_code'     => 'Employee Code',
            'employee_name'     => 'Name',
            'employee_job_code' => 'Job Code',
            'employee_job_type' => 'Job Type',
            'employee_status'   => 'Status',
            'employee_division_code' => 'Division',
            'valid_from'        => 'Valid From',
            'valid_to'          => 'Valid To',
        ];
    }

    protected function csvHeaders(): array
    {
        return [
            'employee_code', 'employee_name',
            'employee_estate_code', 'employee_division_code',
            'employee_sex', 'employee_job_code', 'employee_job_type',
            'employee_status', 'employee_stats', 'employee_profile',
            'employee_department', 'employee_vendor',
            'valid_from', 'valid_to', 'work_permit_exp_date',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['employee_code'] ?? '');
        if (empty($code)) return null;

        return [
            'employee_code'           => strtoupper($code),
            'employee_name'           => trim($row['employee_name'] ?? ''),
            'employee_estate_code'    => strtoupper(trim($row['employee_estate_code'] ?? '')),
            'employee_division_code'  => strtoupper(trim($row['employee_division_code'] ?? '')),
            'employee_sex'            => trim($row['employee_sex'] ?? ''),
            'employee_job_code'       => strtoupper(trim($row['employee_job_code'] ?? '')),
            'employee_job_type'       => trim($row['employee_job_type'] ?? ''),
            'employee_status'         => trim($row['employee_status'] ?? ''),
            'employee_stats'          => trim($row['employee_stats'] ?? ''),
            'employee_profile'        => trim($row['employee_profile'] ?? ''),
            'employee_department'     => trim($row['employee_department'] ?? ''),
            'employee_vendor'         => trim($row['employee_vendor'] ?? ''),
            'is_internal_estate'      => false,
            'valid_from'              => $this->parseDate($row['valid_from'] ?? ''),
            'valid_to'                => $this->parseDate($row['valid_to'] ?? ''),
            'work_permit_exp_date'    => $this->parseDate($row['work_permit_exp_date'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['employee_code'])) return 'Employee Code is required.';
        if (empty($row['employee_name'])) return 'Employee Name is required.';
        return null;
    }

    // Override: employee_code is globally unique — skip duplicates
    public function saveUploadedData(): \Illuminate\Http\RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $rows    = session('master_preview_m_employee', []);
        $saved   = 0;
        $skipped = 0;

        if (empty($rows)) {
            return redirect()->route('masters.employee.index')
                ->with('error', 'No preview data found.');
        }

        DB::transaction(function () use ($rows, &$saved, &$skipped) {
            foreach ($rows as $row) {
                // Check global uniqueness
                $exists = DB::table('m_employee')
                    ->where('employee_code', $row['employee_code'])
                    ->exists();

                if ($exists) {
                    // Update existing
                    DB::table('m_employee')
                        ->where('employee_code', $row['employee_code'])
                        ->update(array_merge($row, [
                            'company_id' => $this->companyId(),
                            'updated_by' => $this->userName(),
                            'updated_at' => now(),
                        ]));
                } else {
                    DB::table('m_employee')->insert(array_merge($row, [
                        'company_id' => $this->companyId(),
                        'created_by' => $this->userName(),
                        'created_at' => now(),
                        'updated_by' => $this->userName(),
                        'updated_at' => now(),
                    ]));
                    $saved++;
                }
            }
            $this->touchLog(['last_updated_at' => now(), 'last_updated_by' => $this->userId(), 'is_replaced' => true]);
        });

        session()->forget('master_preview_m_employee');

        return redirect()->route('masters.employee.index')
            ->with('success', "{$saved} employees inserted, " . (count($rows) - $saved) . " updated.");
    }

    // DataTables with filters
    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()
            ->orderBy('employee_code');

        if ($request->filled('estate_code'))   $query->where('employee_estate_code', $request->estate_code);
        if ($request->filled('division_code')) $query->where('employee_division_code', $request->division_code);
        if ($request->filled('job_code'))      $query->where('employee_job_code', $request->job_code);

        return DataTables::queryBuilder($query)->addIndexColumn()->make(true);
    }

    // Generate QR Code
    public function generateQr(Request $request): JsonResponse
    {
        $request->validate([
            'employee_codes' => 'required|array|min:1|max:50',
        ]);

        $codes = $request->employee_codes;
        $qrData = [];

        foreach ($codes as $code) {
            $emp = DB::table('m_employee')
                ->where('company_id', $this->companyId())
                ->where('employee_code', $code)
                ->first();

            if (!$emp) continue;

            $qrContent = json_encode([
                'type'          => 'EMPLOYEE',
                'employee_code' => $emp->employee_code,
                'employee_name' => $emp->employee_name,
                'company'       => $this->companyCode(),
                'estate'        => $emp->employee_estate_code ?? '',
            ]);

            $svg = QrCode::format('svg')
                ->size(200)
                ->errorCorrection('M')
                ->generate($qrContent);

            $qrData[] = [
                'employee_code' => $emp->employee_code,
                'employee_name' => $emp->employee_name,
                'qr_svg'        => base64_encode($svg),
            ];
        }

        return $this->jsonSuccess('QR codes generated.', $qrData);
    }

    // Lookup for dropdowns
    public function lookup(Request $request): JsonResponse
    {
        $query = DB::table('m_employee')
            ->where('company_id', $this->companyId())
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_to', '>=', now()->toDateString())
            ->orderBy('employee_name');

        if ($request->filled('job_code')) {
            $query->where('employee_job_code', $request->job_code);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('employee_code', 'ilike', "%{$request->search}%")
                  ->orWhere('employee_name', 'ilike', "%{$request->search}%");
            });
        }

        $employees = $query->limit(50)->get(['employee_code', 'employee_name', 'employee_job_code']);

        return $this->jsonSuccess('OK', $employees);
    }

    // ── SAP two-step config ───────────────────────────────────────────────────
    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EMPLOYEE_OUT',
            'urn'     => 'ZEPMS_EMPLOYEE_OUT',
            'filters' => ['BUKRS' => '*', 'LAND1' => '{country_code}'],
            'columns' => ['BUKRS', 'ESTNR', 'DIVNR', 'PRFNR', 'EMPNR', 'ENAME', 'KDATB', 'KDATE',
                          'JBCDE', 'JBTYP', 'SEX', 'STATS', 'WOPXD', 'DEPNR', 'LIFNR'],
            'mapping' => [
                'employee_estate_code'   => 'ESTNR',
                'employee_division_code' => 'DIVNR',
                'employee_profile'       => 'PRFNR',
                'employee_code'          => 'EMPNR',
                'employee_name'          => 'ENAME',
                'employee_job_code'      => 'JBCDE',
                'employee_job_type'      => 'JBTYP',
                'employee_sex'           => 'SEX',
                'employee_status'        => 'STATS',
                'employee_stats'         => 'STATS',   // CI4 maps STATS to both
                'employee_department'    => 'DEPNR',
                'employee_vendor'        => 'LIFNR',
                'valid_from'             => 'KDATB',
                'valid_to'               => 'KDATE',
                'work_permit_exp_date'   => 'WOPXD',
            ],
        ];
    }

    protected function transformSapRow(array $master, array $staging): array
    {
        $master['valid_from']           = $this->parseDate($staging['KDATB'] ?? '');
        $master['valid_to']             = $this->parseDate($staging['KDATE'] ?? '');
        $master['work_permit_exp_date'] = $this->parseDate($staging['WOPXD'] ?? '');
        $master['is_internal_estate']   = false;
        return $master;
    }

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') return null;
        $val = str_replace('.', '-', $val);
        try { return \Carbon\Carbon::parse($val)->toDateString(); }
        catch (\Exception $e) { return null; }
    }
}
