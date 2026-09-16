<?php

namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;
use App\Services\SapService;

class ClosingGoodsReceiptController extends BaseClosingController
{
    protected function table(): string       { return 'tr_gr_header'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'gr_date'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_GOODS_RECEIPT_IN'; }
    protected function sapIm(): string       { return 'IM_GR'; }
    protected function routePrefix(): string { return 'closing.goods_receipt'; }
    protected function viewPrefix(): string  { return 'closing.goods_receipt'; }
    protected function title(): string       { return 'Goods Receipt'; }
    protected function requiresClosingApproval(): bool { return true; }

    protected function buildSapItems(array $rows, array $config): array
    {
        $reqId = SapService::requestId($this->userId());
        $items = [];
        foreach ($rows as $r) {
            $details = DB::table('tr_gr_detail')
                ->where('gr_header_id', $r['id'])
                ->get();

            foreach ($details as $d) {
                $items[] = [
                    'UNIQUE_ID'  => $r['id'],
                    'BUDAT'      => $r['gr_date'] ?? null,
                    'EBELN'      => $r['po_number'] ?? null,
                    'WERKS'      => $r['plant_code'] ?? null,
                    'LGORT'      => $r['sloc_code'] ?? null,
                    'MATNR'      => $d->material_code ?? null,
                    'ERFMG'      => $d->qty ?? null,
                    'ERFME'      => $d->uom ?? null,
                    'REQUEST_ID' => $reqId,
                    'STATE'      => 'C',
                ];
            }
        }
        return $items;
    }
}