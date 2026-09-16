<?php

namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;
use App\Services\SapService;

class ClosingGoodsIssueController extends BaseClosingController
{
    protected function table(): string       { return 'tr_gi_header'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'gi_date'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_GOODS_ISSUE_IN'; }
    protected function sapIm(): string       { return 'IM_GI'; }
    protected function routePrefix(): string { return 'closing.goods_issue'; }
    protected function viewPrefix(): string  { return 'closing.goods_issue'; }
    protected function title(): string       { return 'Goods Issue'; }
    protected function requiresClosingApproval(): bool { return true; }

    protected function buildSapItems(array $rows, array $config): array
    {
        $reqId = SapService::requestId($this->userId());
        $items = [];
        foreach ($rows as $r) {
            $details = DB::table('tr_gi_detail')
                ->where('gi_header_id', $r['id'])
                ->get();

            foreach ($details as $d) {
                $items[] = [
                    'UNIQUE_ID'  => $r['id'],
                    'BUDAT'      => $r['gi_date'] ?? null,
                    'BWART'      => $r['movement_type'] ?? null,
                    'WERKS'      => $r['plant_code'] ?? null,
                    'LGORT'      => $r['sloc_code'] ?? null,
                    'MATNR'      => $d->material_code ?? null,
                    'ERFMG'      => $d->qty ?? null,
                    'ERFME'      => $d->uom ?? null,
                    'KOSTL'      => $d->cost_center ?? null,
                    'POSID'      => $d->wbs_code ?? null,
                    'AUFNR'      => $d->order_number ?? null,
                    'SAKTO'      => $d->gl_account ?? null,
                    'REQUEST_ID' => $reqId,
                    'STATE'      => 'C',
                ];
            }
        }
        return $items;
    }
}