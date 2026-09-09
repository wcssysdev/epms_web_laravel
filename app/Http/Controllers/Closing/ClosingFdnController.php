<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

/**
 * FDN (Delivery Note) closing. UNIQUE_ID per CI3 = fdn_detail_id.
 */
class ClosingFdnController extends BaseClosingController
{
    protected function table(): string       { return 't_fdn'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'created_at'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_FDN_IN'; }
    protected function sapIm(): string       { return 'IM_FDN'; }
    protected function routePrefix(): string { return 'closing.fdn'; }
    protected function viewPrefix(): string  { return 'closing.fdn'; }
    protected function title(): string       { return 'FDN (Delivery Note)'; }
    protected function requiresClosingApproval(): bool { return true; }

    protected function fetchForSap(array $ids, array $config): array
    {
        $rows = DB::table('t_fdn_detail as d')
            ->join('t_fdn as h', 'h.id', '=', 'd.fdn_id')
            ->whereIn('h.id', $ids)
            ->get(['h.*','d.id as detail_id','d.oph_id','d.bunches_delivered','d.loose_fruit_delivered',
                   'd.oph_block_code','d.oph_tph_code','d.oph_card_id'])
            ->map(fn($r)=>(array)$r)->all();

        $loadersByFdn = [];
        foreach ($ids as $fdnId) {
            $loadersByFdn[$fdnId] = DB::table('t_fdn_loader')->where('fdn_id', $fdnId)->orderBy('loader_type')->get()->toArray();
        }
        foreach ($rows as &$r) { $r['_loaders'] = $loadersByFdn[$r['id']] ?? []; }
        unset($r);
        return $rows;
    }

    protected function buildSapItems(array $rows, array $config): array
    {
        $items = [];
        foreach ($rows as $r) {
            $item = [
                'UNIQUE_ID'  => $r['detail_id'],
                'ESTNR'      => $r['estate_code'] ?? null,
                'SPB'        => $r['id'],
                'OPH'        => $r['oph_id'] ?? null,
                'BUDAT'      => $r['created_at'] ? date('Y-m-d', strtotime($r['created_at'])) : null,
                'ABTL'       => $r['division_code'] ?? null,
                'EMPNR_K'    => $r['kerani_kirim_emp_code'] ?? null,
                'LNUM'       => $r['license_number'] ?? null,
                'DEST'       => $r['deliver_to_code'] ?? null,
                'NOTES'      => $r['delivery_note'] ?? null,
                'ZBRUTO'     => $r['bruto'] ?? null,
                'ZTARRA'     => $r['tarra'] ?? null,
                'BLOCK'      => $r['oph_block_code'] ?? null,
                'TPH'        => $r['oph_tph_code'] ?? null,
                'CARD_ID'    => $r['oph_card_id'] ?? null,
                'BUNCHES'    => $r['bunches_delivered'] ?? null,
                'LOOSE_FRUIT'=> $r['loose_fruit_delivered'] ?? null,
            ];
            $loaders = $r['_loaders'] ?? [];
            foreach ($loaders as $l) {
                if ((int)($l->loader_type ?? 0) === 1) {
                    $item['EMPNR1'] = $l->employee_code ?? null;
                } else {
                    $item['EMPNR2'] = $l->employee_code ?? null;
                }
            }
            $items[] = $item;
        }
        return $items;
    }
}
