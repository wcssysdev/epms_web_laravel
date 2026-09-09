<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

class ClosingCoconutFdnController extends BaseClosingController
{
    protected function table(): string       { return 't_coconut_fdn'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'created_at'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_COCONUT_FDN_IN'; }
    protected function sapIm(): string       { return 'IM_FDN'; }
    protected function routePrefix(): string { return 'closing.coconut_fdn'; }
    protected function viewPrefix(): string  { return 'closing.coconut_fdn'; }
    protected function title(): string       { return 'FDN (Coconut)'; }
    protected function requiresClosingApproval(): bool { return true; }

    protected function buildSapItems(array $rows, array $config): array
    {
        return array_map(fn($r) => [
            'UNIQUE_ID'   => $r['id'],
            'ESTNR'       => $r['estate_code'] ?? null,
            'BUDAT'       => $r['created_at'] ? date('Y-m-d', strtotime($r['created_at'])) : null,
            'EMPNR_K'     => $r['kerani_kirim_emp_code'] ?? null,
            'SPB'         => $r['id'],
            'LNUM'        => $r['license_number'] ?? null,
            'DEST'        => $r['destination'] ?? null,
            'QTY'         => $r['total_customer_qty'] ?? null,
            'CARD_ID'     => $r['fdn_card_id'] ?? null,
        ], $rows);
    }
}
