<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

class ClosingVraController extends BaseClosingController
{
    protected function table(): string       { return 't_vra'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'vra_date'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_PM_VRA_CONFIRM_IN'; }
    protected function sapIm(): string       { return 'I_VRA'; }
    protected function routePrefix(): string { return 'closing.vra'; }
    protected function viewPrefix(): string  { return 'closing.vra'; }
    protected function title(): string       { return 'VRA (Equipment Activity)'; }

    protected function fetchForSap(array $ids, array $config): array
    {
        return DB::table('t_vra')
            ->leftJoin('m_vra', DB::raw('trim(t_vra.order_number)'), '=', DB::raw('trim(m_vra.vra_order_number)'))
            ->leftJoin('m_meas_point', 'm_meas_point.equipment_code', '=', 'm_vra.equipment_code')
            ->leftJoin('m_work_center', 'm_work_center.work_center_code', '=', DB::raw("''"))  // no direct FK
            ->whereIn('t_vra.id', $ids)
            ->get(['t_vra.*', 'm_meas_point.unit as meas_unit', 'm_meas_point.point as meas_point_val'])
            ->map(fn($r)=>(array)$r)->all();
    }

    protected function buildSapItems(array $rows, array $config): array
    {
        return array_map(fn($r) => [
            'AUFNR'     => $r['order_number'] ?? null,
            'BUDAT'     => $r['vra_date'] ?? null,
            'ARBPL'     => null,  // work_center not stored in simplified Laravel schema
            'ISMNE'     => $r['meas_unit'] ?? $r['meas_point'] ?? null,
            'ISMNW'     => $r['reading_value'] ?? null,
            'ISDD'      => $r['vra_date'] ?? null,
            'IEDD'      => $r['vra_date'] ?? null,
            'ISDZ'      => null,
            'IEDZ'      => null,
            'GRUND'     => null,
            'LTXA1'     => $r['confirmation_text'] ?? null,
            'ctext2'    => null,
            'UNIQUE_ID' => $r['id'],
        ], $rows);
    }
}
