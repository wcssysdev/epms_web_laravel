<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

/**
 * CP1 and CP2 share the same SAP endpoint. cp_type is included in payload (TYPE).
 * UNIQUE_ID per CI3 = cp_detail_id (not cp_id).
 */
class ClosingCpController extends BaseClosingController
{
    protected function table(): string       { return 't_cp'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'created_at'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_CP_IN'; }
    protected function sapIm(): string       { return 'IM_CP'; }
    protected function routePrefix(): string { return 'closing.cp'; }
    protected function viewPrefix(): string  { return 'closing.cp'; }
    protected function title(): string       { return 'Checkpoint (CP1 & CP2)'; }
    protected function requiresClosingApproval(): bool { return true; }

    protected function fetchForSap(array $ids, array $config): array
    {
        // Per CI3: unit of sending is cp_detail rows joined to cp header.
        $rows = DB::table('t_cp_detail as d')
            ->join('t_cp as h', 'h.id', '=', 'd.cp_id')
            ->whereIn('h.id', $ids)
            ->get(['h.*','d.id as detail_id','d.oph_id','d.bunches_delivered','d.loose_fruit_delivered','d.oph_block_code','d.oph_tph_code'])
            ->map(fn($r)=>(array)$r)->all();

        // Attach loaders per cp_id
        $loadersBycp = [];
        foreach ($ids as $cpId) {
            $loadersBycp[$cpId] = DB::table('t_cp_loader')->where('cp_id', $cpId)->orderBy('loader_type')->get()->toArray();
        }

        foreach ($rows as &$r) { $r['_loaders'] = $loadersBycp[$r['id']] ?? []; }
        unset($r);
        return $rows;
    }

    protected function buildSapItems(array $rows, array $config): array
    {
        $items = [];
        foreach ($rows as $r) {
            $item = [
                'TYPE'      => $r['cp_type'] ?? null,
                'UNIQUE_ID' => $r['detail_id'],
                'ESTNR'     => $r['estate_code'] ?? null,
                'CHECK_NOTE'=> $r['id'],      // cp_id
                'OPH'       => $r['oph_id'] ?? null,
                'BUDAT'     => $r['created_at'] ? date('Y-m-d', strtotime($r['created_at'])) : null,
                'ABTL'      => $r['division_code'] ?? null,
                'EMPNR_K'   => $r['kerani_kirim_emp_code'] ?? null,
                'LNUM'      => $r['license_number'] ?? null,
                'RPT'       => $r['receiving_point_code'] ?? null,
                'NOTES'     => $r['delivery_note'] ?? null,
                'ZBRUTO'    => $r['bruto'] ?? null,
                'ZTARRA'    => $r['tarra'] ?? null,
                'BLOCK'     => $r['oph_block_code'] ?? null,
                'TPH'       => $r['oph_tph_code'] ?? null,
            ];
            // Add loaders as EMPNR1, EMPNR2... per CI3
            $loaders = $r['_loaders'] ?? [];
            $counter = 1;
            foreach ($loaders as $l) {
                if ((int)($l->loader_type ?? 0) === 1) {
                    $item['EMPNR1'] = $l->employee_code ?? null;
                } else {
                    $item['EMPNR' . (++$counter)] = $l->employee_code ?? null;
                }
            }
            $items[] = $item;
        }
        return $items;
    }
}
