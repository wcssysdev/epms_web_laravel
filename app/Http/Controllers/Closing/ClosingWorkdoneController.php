<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

class ClosingWorkdoneController extends BaseClosingController
{
    protected function table(): string       { return 't_workdone'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'workdone_date'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_GENERAL_IN'; }
    protected function sapIm(): string       { return 'IM_GW'; }
    protected function routePrefix(): string { return 'closing.workdone'; }
    protected function viewPrefix(): string  { return 'closing.workdone'; }
    protected function title(): string       { return 'Work Completion'; }

    protected function fetchForSap(array $ids, array $config): array
    {
        return DB::table('t_workdone')
            ->leftJoin('m_employee','m_employee.employee_code','=','t_workdone.employee_code')
            ->whereIn('t_workdone.id', $ids)
            ->get(['t_workdone.*','m_employee.employee_profile as employee_profile'])
            ->map(fn($r)=>(array)$r)->all();
    }

    protected function buildSapItems(array $rows, array $config): array
    {
        return array_map(fn($r) => [
            'EMPNR'     => $r['employee_code'] ?? null,
            'BUDAT'     => $r['workdone_date'] ?? null,
            'ACTVT_NO'  => $r['activity_code'] ?? null,
            'WERKS'     => $r['plant_code'] ?? null,
            'AUFNR'     => $r['order_number'] ?? null,
            'ANLN1'     => $r['auc_number'] ?? null,
            'KOSTL'     => $r['cost_center'] ?? null,
            'BLOCK'     => $r['block_code'] ?? null,
            'MENGE'     => $r['qty'] ?? null,
            'AMEIN'     => $r['activity_uom'] ?? null,
            'PRATE'     => $r['flexrate'] ?? null,
            'REASN'     => $r['description'] ?? null,
            'UNIQUE_ID' => $r['id'],
            'PRFNR'     => $r['employee_profile'] ?? null,
            'BLK_TYP'   => $r['block_status'] ?? 0,
            'HKINP'     => $r['mandays'] ?? null,
            'KUNNR'     => $r['customer_code'] ?? null,
            'PS_POSID'  => $r['wbs_code'] ?? null,
        ], $rows);
    }
}
