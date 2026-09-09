<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

class ClosingOphController extends BaseClosingController
{
    protected function table(): string       { return 't_oph'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'created_at'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_HARVESTING_IN'; }
    protected function sapIm(): string       { return 'IM_HV'; }
    protected function routePrefix(): string { return 'closing.oph'; }
    protected function viewPrefix(): string  { return 'closing.oph'; }
    protected function title(): string       { return 'OPH (Palm)'; }
    protected function requiresClosingApproval(): bool { return true; }

    protected function fetchForSap(array $ids, array $config): array
    {
        return DB::table('t_oph')
            ->leftJoin('t_oph_persons', function($j){
                $j->on('t_oph_persons.oph_id','=','t_oph.id')->where('t_oph_persons.person_type',1);
            })
            ->whereIn('t_oph.id', $ids)
            ->get(['t_oph.*',
                   't_oph_persons.employee_code as cutter_employee_code',
                   't_oph_persons.percentage as cutter_percentage'])
            ->map(fn($r)=>(array)$r)->all();
    }

    protected function buildSapItems(array $rows, array $config): array
    {
        $items = [];
        foreach ($rows as $r) {
            $r['UNIQUE_ID'] = $r['id'];
            $items[] = [
                'BUDAT'     => $r['created_at'] ? date('Y-m-d', strtotime($r['created_at'])) : null,
                'OPH'       => $r['id'],
                'TPH'       => $r['tph_code'] ?? null,
                'ESTNR'     => $r['estate_code'] ?? null,
                'WERKS'     => $r['plant_code'] ?? null,
                'ABTL'      => $r['division_code'] ?? null,
                'BLOCK'     => $r['block_code'] ?? null,
                'EMPNR_M'   => $r['mandor_employee_code'] ?? null,
                'EMPNR_K'   => $r['kerani_panen_employee_code'] ?? null,
                'EMPNR'     => $r['cutter_employee_code'] ?? null,
                'ZRIPE'     => $r['bunches_ripe'] ?? 0,
                'ZOVERIPE'  => $r['bunches_overripe'] ?? 0,
                'ZUNDERIPE' => $r['bunches_underripe'] ?? 0,
                'ZUNRIPE'   => $r['bunches_unripe'] ?? 0,
                'ZROTTEN'   => $r['bunches_rotten'] ?? 0,
                'ZWET'      => $r['bunches_wet'] ?? 0,
                'ZLONGSTLK' => $r['bunches_long_stalk'] ?? 0,
                'ZEMPTY'    => $r['bunches_empty'] ?? 0,
                'ZDIRTY'    => $r['bunches_dirty'] ?? 0,
                'ZUNFRSH'   => $r['bunches_unfresh'] ?? 0,
                'ZOLD'      => $r['bunches_old'] ?? 0,
                'ZPSTDMG'   => ($r['bunches_pest_damaged_old'] ?? 0) + ($r['bunches_pest_damaged_new'] ?? $r['bunches_pest_damaged'] ?? 0),
                'ZDISEASD'  => $r['bunches_diseased'] ?? 0,
                'ZBRONDOLAN'=> $r['loose_fruits'] ?? 0,
                'UNIQUE_ID' => $r['id'],
            ];
        }
        return $items;
    }
}
