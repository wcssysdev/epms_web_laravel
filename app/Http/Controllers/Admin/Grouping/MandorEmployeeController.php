<?php

namespace App\Http\Controllers\Admin\Grouping;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class MandorEmployeeController extends BaseGroupingController
{
    protected function tableName(): string    { return 't_user_assignment'; }
    protected function resourceName(): string { return 'Grouping Mandor - Employee'; }
    protected function viewPrefix(): string   { return 'admin.grouping.mandor_employee'; }
    protected function routePrefix(): string  { return 'grouping.mandor_employee'; }

    protected function datatableColumns(): array
    {
        return [
            'profile_name'          => 'Profile Name',
            'mandor_display'        => 'Mandor',
            'employee_profile'      => 'Employee Profile Name',
            'employee_display'      => 'Employee',
            'validity_period'       => 'Validity Period',
            'created_time'          => 'Created Time',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'grouping_mandor_employee_code' => 'required|string|max:100',
            'grouping_employee_code'        => 'required|string|max:100',
            'grouping_assignment_from'      => 'required|date_format:Y-m-d',
            'grouping_assignment_to'        => 'required|date_format:Y-m-d',
        ];
    }

    protected function storeData(Request $request): array
    {
        $companyCode = $this->companyCode();
        $mandorCode = strtoupper(trim($request->grouping_mandor_employee_code));
        $empCode    = strtoupper(trim($request->grouping_employee_code));

        $mandor = DB::table('m_employee')
            ->where('employee_code', $mandorCode)
            ->when($companyCode, fn($q) => $q->where('employee_code', 'LIKE', $companyCode . '%'))
            ->first();

        $emp = DB::table('m_employee')
            ->where('employee_code', $empCode)
            ->when($companyCode, fn($q) => $q->where('employee_code', 'LIKE', $companyCode . '%'))
            ->first();

        $profileName = $this->companyConfig?->estate_name ?? session('estate_name', 'ESTATE');

        return [
            'profile_name'          => $profileName,
            'mandor_employee_code'  => $mandorCode,
            'mandor_employee_name'  => $mandor?->employee_name ?? $mandorCode,
            'employee_profile'      => $emp?->employee_profile ?? $profileName,
            'employee_code'         => $empCode,
            'employee_name'         => $emp?->employee_name ?? $empCode,
            'worker_employee_code'  => $empCode,
            'worker_employee_name'  => $emp?->employee_name ?? $empCode,
            'start_validity'        => $request->grouping_assignment_from,
            'end_validity'          => $request->grouping_assignment_to,
            'assignment_valid_from' => $request->grouping_assignment_from,
            'assignment_valid_to'   => $request->grouping_assignment_to,
        ];
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()
            ->select([
                'id',
                'profile_name',
                'mandor_employee_code',
                'mandor_employee_name',
                'employee_profile',
                'employee_code',
                'employee_name',
                'start_validity',
                'end_validity',
                'created_at',
                'updated_at',
            ]);

        return DataTables::query($query)
            ->addIndexColumn()
            ->addColumn('mandor_display', function ($row) {
                return ($row->mandor_employee_code ?? '') . ' - ' . ($row->mandor_employee_name ?? '');
            })
            ->addColumn('employee_display', function ($row) {
                return ($row->employee_code ?? '') . ' - ' . ($row->employee_name ?? '');
            })
            ->addColumn('validity_period', function ($row) {
                $from = $row->start_validity ? substr($row->start_validity, 0, 10) : '';
                $to   = $row->end_validity ? substr($row->end_validity, 0, 10) : '';
                return ($from || $to) ? "{$from} - {$to}" : '-';
            })
            ->addColumn('created_time', function ($row) {
                return $row->created_at ? substr((string)$row->created_at, 0, 19) : '-';
            })
            ->rawColumns(['mandor_display', 'employee_display', 'validity_period', 'created_time'])
            ->make(true);
    }

    protected function hasGetFromSap(): bool
    {
        return true;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_MEMBER_OUT',
            'urn'     => 'ZEPMS_MEMBER_OUT',
            'filters' => ['PRFNR' => '{estate_name}'],
            'columns' => ['PRFNR', 'EMPNR', 'EMPNR_M', 'KDATB', 'KDATE'],
            'mapping' => [
                'profile_name'          => 'PRFNR',
                'employee_code'         => 'EMPNR',
                'mandor_employee_code'  => 'EMPNR_M',
                'worker_employee_code'  => 'EMPNR',
                'start_validity'        => 'KDATB',
                'end_validity'          => 'KDATE',
                'assignment_valid_from' => 'KDATB',
                'assignment_valid_to'   => 'KDATE',
            ],
        ];
    }

    protected function transformSapRow(array $master, array $staging): array
    {
        $companyCode = $this->companyCode();

        $mandorEmp = DB::table('m_employee')
            ->where('employee_code', $master['mandor_employee_code'])
            ->when($companyCode, fn($q) => $q->where('employee_code', 'LIKE', $companyCode . '%'))
            ->first();

        $workerEmp = DB::table('m_employee')
            ->where('employee_code', $master['employee_code'])
            ->when($companyCode, fn($q) => $q->where('employee_code', 'LIKE', $companyCode . '%'))
            ->first();

        $master['mandor_employee_name'] = $mandorEmp?->employee_name ?? $master['mandor_employee_code'];
        $master['employee_name']        = $workerEmp?->employee_name ?? $master['employee_code'];
        $master['worker_employee_name']  = $master['employee_name'];
        $master['employee_profile']     = $workerEmp?->employee_profile ?? ($staging['PRFNR'] ?? $master['profile_name']);

        return $master;
    }

    /**
     * AJAX auto-suggest & lookup for Mandor (Gang)
     */
    public function mandorLookup(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        $query = DB::table('m_employee')
            ->where('company_id', $this->companyId())
            ->when($this->companyCode(), fn($qry) => $qry->where('employee_code', 'LIKE', $this->companyCode() . '%'))
            ->where(function ($qry) {
                $qry->where('employee_job_code', 'LIKE', '%mandor%')
                    ->orWhere('employee_job_code', 'LIKE', '%mandore%');
            })
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_to', '>=', now()->toDateString())
            ->orderBy('employee_name');

        if (mb_strlen($q) >= 1) {
            $query->where(function ($sub) use ($q) {
                $sub->where('employee_code', 'LIKE', "%{$q}%")
                    ->orWhere('employee_name', 'LIKE', "%{$q}%");
            });
        }

        $mandors = $query->limit(50)->get([
            'employee_code',
            'employee_name',
            'employee_job_code',
        ]);

        return $this->jsonSuccess('OK', $mandors);
    }

    /**
     * AJAX auto-suggest & lookup for Employee
     */
    public function employeeLookup(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        $query = DB::table('m_employee')
            ->where('company_id', $this->companyId())
            ->when($this->companyCode(), fn($qry) => $qry->where('employee_code', 'LIKE', $this->companyCode() . '%'))
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_to', '>=', now()->toDateString())
            ->orderBy('employee_name');

        if (mb_strlen($q) >= 1) {
            $query->where(function ($sub) use ($q) {
                $sub->where('employee_code', 'LIKE', "%{$q}%")
                    ->orWhere('employee_name', 'LIKE', "%{$q}%");
            });
        }

        $employees = $query->limit(50)->get([
            'employee_code',
            'employee_name',
            'employee_job_code',
            'employee_profile',
        ]);

        return $this->jsonSuccess('OK', $employees);
    }
}
