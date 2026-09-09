<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

class ClosingCoconutChitController extends BaseClosingController
{
    protected function table(): string       { return 't_coconut_oph'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'created_at'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_CHT_IN'; }
    protected function sapIm(): string       { return 'IM_GW'; }
    protected function routePrefix(): string { return 'closing.coconut_chit'; }
    protected function viewPrefix(): string  { return 'closing.coconut_chit'; }
    protected function title(): string       { return 'Coconut Harvesting Chit'; }
    protected function requiresClosingApproval(): bool { return true; }

    protected function buildSapItems(array $rows, array $config): array
    {
        return array_map(fn($r) => [
            'UNIQUE_ID'   => $r['id'],
            'ESTNR'       => $r['estate_code'] ?? null,
            'BUDAT'       => $r['created_at'] ? date('Y-m-d', strtotime($r['created_at'])) : null,
            'ABTL'        => $r['division_code'] ?? null,
            'BLOCK'       => $r['block_code'] ?? null,
            'TPH'         => $r['tph_code'] ?? null,
            'EMPNR_K'     => $r['checker_employee_code'] ?? null,
            'NUTS_TOTAL'  => $r['nuts_total'] ?? null,
            'CARD_ID'     => $r['oph_card_id'] ?? null,
        ], $rows);
    }
}
