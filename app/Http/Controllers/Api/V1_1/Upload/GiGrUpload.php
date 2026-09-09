<?php

namespace App\Http\Controllers\Api\V1_1\Upload;

use App\Models\Transaction\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Handles GI (Goods Issue) and GR (Goods Receipt) upload from mobile.
 * Replicates the CI3 save_transaction_datas_gigr() logic.
 *
 * CI3 bucket keys: data_t_gi[] and data_t_gr[].
 * Mobile sends mapped field names (t_gi_*, t_gr_*, material sub-array).
 * This saves to tr_gi_header + tr_gi_detail and tr_gr_header + tr_gr_detail.
 *
 * Logic:
 *  - GI: skip if tr_gi_header with same number already exists.
 *  - GR: always insert (accumulate GR against a PO).
 *  - Returns HTTP OK / Failed / No Data (matches CI3 responses).
 */
final class GiGrUpload
{
    public function __construct(
        private ?int $companyId,
        private User $user,
    ) {}

    /**
     * @return 'ok'|'failed'|'nodata'
     */
    public function handle(array $data): string
    {
        $gi = $data['data_t_gi'] ?? [];
        $gr = $data['data_t_gr'] ?? [];
        if (empty($gi) && empty($gr)) {
            return 'nodata';
        }

        try {
            DB::transaction(function () use ($gi, $gr) {
                $this->saveGi($gi);
                $this->saveGr($gr);
            });
            return 'ok';
        } catch (\Throwable $e) {
            return 'failed';
        }
    }

    private function saveGi(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $docNumber = Normalizer::str($r, 't_gi_doc_id') ?? Normalizer::str($r, 'tr_gi_header_number');
            if ($docNumber === null) continue;

            // Skip duplicates (CI3 checks res_cek_no).
            if (DB::table('tr_gi_header')->where('gi_document_number', $docNumber)->exists()) continue;

            // tr_gi_header.id is VARCHAR NULL-default — generate application-side.
            $headerId = 'GI' . now()->format('YmdHis') . random_int(100, 999);
            DB::table('tr_gi_header')->insert([
                'id'                 => $headerId,
                'company_id'         => $this->companyId,
                'gi_date'            => Normalizer::date($r, 'tr_gi_header_pstg_date') ?? Carbon::today()->toDateString(),
                'estate_code'        => null,
                'plant_code'         => Normalizer::str($r, 't_gi_plant') ?? Normalizer::str($r, 'tr_gi_header_plant_code'),
                'sloc_code'          => Normalizer::str($r, 't_gi_storage_location_from_code') ?? Normalizer::str($r, 'tr_gi_header_sloc_code'),
                'movement_type'      => Normalizer::str($r, 't_gi_movement_type') ?? Normalizer::str($r, 'tr_gi_header_mvt_code'),
                'gi_document_number' => $docNumber,
                'is_approved'        => false,
                'integration_status' => 0,
                'created_by'         => Normalizer::str($r, 't_gi_created_by') ?? Normalizer::str($r, 'tr_gi_header_created_by') ?? $this->actor(),
                'updated_by'         => $this->actor(),
                'created_at'         => Carbon::now(),
                'updated_at'         => Carbon::now(),
            ]);

            // Material lines (CI3: $arr_date['material'] or 't_gi_material').
            foreach ($this->parseMaterials($r, 't_gi_material') as $m) {
                if (! is_array($m)) continue;
                DB::table('tr_gi_detail')->insert([
                    'company_id'     => $this->companyId,
                    'gi_header_id'   => $headerId,
                    'material_code'  => Normalizer::str($m, 'tr_gi_detail_material_code') ?? Normalizer::str($m, 'material_code') ?? '',
                    'material_name'  => Normalizer::str($m, 'tr_gi_detail_material_name') ?? Normalizer::str($m, 'material_name') ?? '',
                    'qty'            => Normalizer::floatOr0($m, 'tr_gi_detail_qty') ?: Normalizer::floatOr0($m, 'material_take_qty'),
                    'uom'            => Normalizer::str($m, 'tr_gi_detail_uom') ?? Normalizer::str($m, 'material_uom') ?? '',
                    'cost_center'    => Normalizer::str($r, 't_gi_cost_center') ?? Normalizer::str($r, 'tr_gi_header_cost_center'),
                    'wbs_code'       => Normalizer::str($r, 't_gi_wbs') ?? Normalizer::str($r, 'tr_gi_header_wbs_code'),
                    'order_number'   => Normalizer::str($r, 't_gi_order_number') ?? Normalizer::str($r, 'tr_gi_header_order_number'),
                    'gl_account'     => Normalizer::str($r, 't_gi_wbs_gl_acc_code') ?? Normalizer::str($r, 'tr_gi_header_gl_account'),
                    'integration_status' => 0,
                ]);
            }
        }
    }

    private function saveGr(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $docNumber = Normalizer::str($r, 't_gr_doc_id') ?? Normalizer::str($r, 'tr_gr_header_number');
            if ($docNumber === null) continue;

            // tr_gr_header.id is VARCHAR NULL-default — generate application-side.
            $headerId = 'GR' . now()->format('YmdHis') . random_int(100, 999);
            DB::table('tr_gr_header')->insert([
                'id'                 => $headerId,
                'company_id'         => $this->companyId,
                'gr_date'            => Normalizer::date($r, 'tr_gr_header_pstg_date') ?? Carbon::today()->toDateString(),
                'plant_code'         => Normalizer::str($r, 't_gr_plant') ?? Normalizer::str($r, 'tr_gr_header_plant_code'),
                'sloc_code'          => Normalizer::str($r, 't_gr_storage_location_code') ?? Normalizer::str($r, 'tr_gr_header_sloc_code'),
                'po_number'          => Normalizer::str($r, 't_gr_material_doc_code') ?? Normalizer::str($r, 'tr_gr_header_order_number'),
                'gr_document_number' => $docNumber,
                'integration_status' => 0,
                'created_by'         => Normalizer::str($r, 't_gr_created_by') ?? Normalizer::str($r, 'tr_gr_header_created_by') ?? $this->actor(),
                'updated_by'         => $this->actor(),
                'created_at'         => Carbon::now(),
                'updated_at'         => Carbon::now(),
            ]);

            foreach ($this->parseMaterials($r, 't_gr_material') as $m) {
                if (! is_array($m)) continue;
                DB::table('tr_gr_detail')->insert([
                    'company_id'    => $this->companyId,
                    'gr_header_id'  => $headerId,
                    'material_code' => Normalizer::str($m, 'tr_gr_detail_material_code') ?? Normalizer::str($m, 'm_po_detail_material_code') ?? '',
                    'material_name' => Normalizer::str($m, 'tr_gr_detail_material_name') ?? Normalizer::str($m, 'm_po_detail_material_name') ?? '',
                    'qty'           => Normalizer::floatOr0($m, 'tr_gr_detail_qty') ?: Normalizer::floatOr0($m, 'material_receive_qty'),
                    'uom'           => Normalizer::str($m, 'tr_gr_detail_uom') ?? Normalizer::str($m, 'm_po_detail_uom') ?? '',
                    'integration_status' => 0,
                ]);
            }
        }
    }

    /** Parse material sub-array: handles both JSON-string and array forms. */
    private function parseMaterials(array $r, string $key): array
    {
        $mats = $r[$key] ?? [];
        if (is_string($mats) && $mats !== '') {
            $decoded = json_decode($mats, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($mats) ? $mats : [];
    }

    private function actor(): string
    {
        return (string) ($this->user->user_employee_code ?: $this->user->user_name ?: $this->user->id);
    }
}
