<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

class ClosingOvertimeController extends BaseClosingController
{
    protected function table(): string       { return 't_overtime'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'overtime_date'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_OVERTIME_IN'; }
    protected function sapIm(): string       { return 'IM_OT'; }
    protected function routePrefix(): string { return 'closing.overtime'; }
    protected function viewPrefix(): string  { return 'closing.overtime'; }
    protected function title(): string       { return 'Work Overtime'; }
    protected function stripEstatePrefix(): bool { return true; }

    protected function fetchForSap(array $ids, array $config): array
    {
        return DB::table('t_overtime')
            ->leftJoin('m_employee','m_employee.employee_code','=','t_overtime.employee_code')
            ->whereIn('t_overtime.id', $ids)
            ->get(['t_overtime.*','m_employee.employee_profile'])
            ->map(fn($r)=>(array)$r)->all();
    }

    protected function buildSapItems(array $rows, array $config): array
    {
        $estate = $config['estate_code'] ?? '';
        return array_map(fn($r) => [
            'EMPNR'     => $r['employee_code'] ?? null,
            'BUDAT'     => $r['overtime_date'] ?? null,
            'ACTVT'     => $r['activity_code'] ?? null,
            'UZEIB'     => $r['start_time'] ?? null,
            'UZEIE'     => $r['end_time'] ?? null,
            'NHOUR'     => $r['duration_hours'] ?? null,
            'OTDES'     => $r['remark'] ?? null,
            'AUFNR'     => $r['order_number'] ?? null,
            'KOSTL'     => $r['cost_center'] ?? null,
            'BLOCK'     => $r['block_code'] ?? null,
            'AMEIN'     => null,
            'REASN'     => $r['remark'] ?? null,
            'UNIQUE_ID' => $estate . $r['id'],  // prepend estate, stripped on response
            'PRFNR'     => $r['employee_profile'] ?? null,
            'BLK_TYP'   => 0,
        ], $rows);
    }
}
