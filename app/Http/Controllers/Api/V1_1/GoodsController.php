<?php

namespace App\Http\Controllers\Api\V1_1;

use App\Models\Transaction\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * EPMS Mobile API — Goods (GR/GI master data).
 * Replicates CI3 Goods::master_post().
 *
 * POST /api/v1_1/goods/master  (token-protected)
 * Body: employee_code (required), sloc_code (optional)
 *
 * Returns: $data["master"] with M_Wbs_Schema, M_Uom_Schema, M_Mvt_type_Schema,
 * M_Cost_center_Schema, M_Material_stock_Schema, M_Gl_account_Schema,
 * M_Gl_account_order_Schema, M_Material_type_Schema, M_Purchase_order_Schema,
 * M_Maintenance_order_Schema, T_GI_plant_Schema, M_GI_Schema, M_Block_Schema,
 * M_Worktype_Schema.
 */
class GoodsController extends ApiController
{
    public function master(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('api_user');

        $v = Validator::make($request->all(), [
            'employee_code' => 'required|max:50',
        ]);
        if ($v->fails()) {
            return $this->respondMessage($v->errors()->first(), self::HTTP_BAD_REQUEST);
        }

        // Verify user is warehouse_clerk or store_clerk (role 23 / 33).
        if (! in_array($user->role_code, ['warehouse_clerk', 'store_clerk'], true)) {
            return $this->respondMessage('Invalid Role', self::HTTP_BAD_REQUEST);
        }

        DB::table('res_data')->insert([
            'res_text'      => json_encode($request->post()),
            'res_timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $cfg   = $user->companyConfig;
        $plant = $cfg?->plant_code ?? '';
        $estate = $cfg?->estate_code ?? '';
        $companyId = $user->company_id;

        $data['master'] = [
            'M_Wbs_Schema'              => $this->getWbs($companyId),
            'M_Uom_Schema'              => $this->getUom(),
            'M_Mvt_type_Schema'         => $this->getMvtType(),
            'M_Cost_center_Schema'      => [],   // m_cost_center not in this schema; return empty
            'M_Material_stock_Schema'   => $this->getMaterialStock($plant, $companyId),
            'M_Gl_account_Schema'       => $this->getGlAcc($companyId),
            'M_Gl_account_order_Schema' => $this->getGlAccOrder($companyId),
            'M_Material_type_Schema'    => $this->getMatType($companyId),
            'M_Purchase_order_Schema'   => $this->getPo($plant, $companyId),
            'M_Maintenance_order_Schema'=> $this->getMo($plant, $companyId),
            'T_GI_plant_Schema'         => $this->getGiPlan($plant, $companyId),
            'M_GI_Schema'               => $this->getGiFinished($companyId),
            'M_Block_Schema'            => $this->getBlock($estate, $companyId),
            'M_Worktype_Schema'         => $this->getWorktype($companyId),
        ];

        return $this->respond($data, self::HTTP_OK);
    }

    private function getWbs(?int $cid): array
    {
        return DB::table('m_gigr_wbs')
            ->when($cid, fn($q)=>$q->where('company_id',$cid))
            ->orderBy('wbs_code')
            ->get()
            ->map(fn($r)=>[
                'wbs_id'          => (int) $r->id,
                'wbs'             => $r->wbs_code,
                'post1'           => $r->wbs_code2,
                'pbukr'           => null,
                'pgsbr'           => null,
                'loevm'           => $r->wbs_code2,
                'wbs_gl_acc_code' => $r->wbs_gl_acc_code,
                'wbs_gl_acc_desc' => $r->wbs_gl_acc_desc,
            ])->all();
    }

    private function getUom(): array
    {
        return DB::table('m_uom')->orderBy('uom_code')
            ->get(['id','uom_code','uom_desc'])
            ->map(fn($r)=>['uom_id'=>(int)$r->id,'uom_code'=>$r->uom_code,'uom_desc'=>$r->uom_desc])
            ->all();
    }

    private function getMvtType(): array
    {
        return DB::table('m_movement_type')
            ->whereIn('mvt_type_code', ['201','221','261'])
            ->orderBy('mvt_type_code')
            ->get()
            ->map(fn($r)=>[
                'mvt_type_id'   => (int) $r->id,
                'mvt_type_code' => $r->mvt_type_code,
                'mvt_type_desc' => $r->mvt_type_desc,
            ])->all();
    }

    private function getMaterialStock(string $plant, ?int $cid): array
    {
        $rows = DB::table('m_material')
            ->when($plant, fn($q)=>$q->where('plant_code',$plant))
            ->orderBy('material_code')
            ->get(['material_code','material_name','material_uom','material_batch'])
            ->all();
        $out = [];
        foreach ($rows as $i => $r) {
            $batch = (empty($r->material_batch) ? '0' : $r->material_batch);
            $name  = trim(str_replace($r->material_code, '', $r->material_name));
            $out[] = [
                'material_stock_id'  => (int) $i,
                'material_code'      => (string) trim($r->material_code),
                'material_name'      => trim($r->material_code . ' ' . $name),
                'material_uom'       => $r->material_uom,
                'material_batch'     => $batch,
            ];
        }
        return $out;
    }

    private function getGlAcc(?int $cid): array
    {
        return DB::table('m_glacc')
            ->when($cid, fn($q)=>$q->where('company_id',$cid))
            ->orderBy('account_number')
            ->get()
            ->map(fn($r)=>[
                'm_glacc_id'         => (int) $r->id,
                'm_glacc_code'       => $r->account_number,
                'm_glacc_acc'        => $r->account_number,
                'm_glacc_group_code' => '-',
                'm_glacc_desc'       => $r->account_desc,
            ])->all();
    }

    private function getGlAccOrder(?int $cid): array
    {
        return DB::table('m_glacc_gi_order')
            ->when($cid, fn($q)=>$q->where('company_id',$cid))
            ->orderBy('account_number')
            ->get()
            ->map(fn($r)=>[
                'm_glacc_id'         => (int) $r->id,
                'm_glacc_code'       => $r->account_number,
                'm_glacc_acc'        => $r->account_number,
                'm_glacc_group_code' => '-',
                'm_glacc_desc'       => $r->account_desc,
            ])->all();
    }

    private function getMatType(?int $cid): array
    {
        return DB::table('material_type_group')
            ->when($cid, fn($q)=>$q->where('company_id',$cid))
            ->orderBy('mat_type_code')
            ->get()
            ->map(fn($r)=>[
                'mat_type_id'   => (int) $r->id,
                'mat_type_code' => $r->mat_type_code,
                'mat_type_desc' => $r->mat_type_desc,
                'mat_code_list' => $r->mat_code_list,
            ])->all();
    }

    private function getPo(string $plant, ?int $cid): array
    {
        $rows = DB::table('m_purchase_order')
            ->when($plant, fn($q)=>$q->where('plant_code',$plant))
            ->where('is_deleted', false)
            ->where('po_type', 'ZNPO')
            ->where('material_code', '!=', '')
            ->orderBy('po_number')
            ->get();
        $grouped = [];
        foreach ($rows as $r) {
            $no = (int) $r->po_number;
            if (empty($grouped[$no])) {
                $grouped[$no] = [
                    'm_po_header_id'     => $no,
                    'header'             => [
                        'm_po_header_id'      => $no,
                        'm_po_header_number'  => $r->po_number,
                        'm_po_header_date'    => $r->sap_created_date,
                        'm_po_header_type'    => $r->po_type,
                        'm_po_header_status'  => $r->po_status,
                        'm_po_header_vendor'  => $r->vendor_name,
                        'm_po_header_plant_code' => $r->plant_code,
                        'm_po_header_sloc_code'  => $r->sloc_code,
                    ],
                    'details'            => [],
                ];
            }
            $grouped[$no]['details'][] = [
                'm_po_detail_material_code' => $r->material_code,
                'm_po_detail_material_name' => $r->material_name,
                'm_po_detail_line_no'       => $r->material_line_num,
                'm_po_detail_batch'         => '',
                'm_po_detail_qty_order'     => $r->qty_order,
                'm_po_detail_uom'           => $r->uom,
                'm_po_detail_qty_deliv'     => $r->qty_order,
            ];
        }
        return array_values($grouped);
    }

    private function getMo(string $plant, ?int $cid): array
    {
        $rows = DB::table('m_maintenance_order')
            ->when($plant, fn($q)=>$q->where('plant_code',$plant))
            ->orderBy('order_number')
            ->get();
        $grouped = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            $grouped[$id] = [
                'm_order_header_id' => $id,
                'header'            => [
                    'm_order_header_id'  => $id,
                    'mo_order_number'    => $r->order_number,
                    'mo_order_desc'      => $r->order_desc,
                    'mo_created_timestamp'=> $r->created_at,
                    'mo_sales_doc_type'  => $r->sales_doc_type,
                    'mo_plant_code'      => $r->plant_code,
                    'mo_business_area'   => $r->business_area,
                    'mo_company_code'    => null,
                    'mo_flag'            => 'MO',
                ],
                'details'           => [],
            ];
        }
        return array_values($grouped);
    }

    private function getGiPlan(string $plant, ?int $cid): array
    {
        // Pending GI plans (approved, not yet in tr_gi_header, not closed).
        // tr_gi_plan doesn't have a direct document number link to tr_gi_header
        // in the redesigned schema, so we return all approved open plans.
        $rows = DB::table('tr_gi_plan_detail as tgd')
            ->join('tr_gi_plan as tgh', 'tgd.gi_plan_id', '=', 'tgh.id')
            ->where('tgh.is_approved', true)
            ->when($plant, fn($q)=>$q->where('tgh.plant_code', $plant))
            ->when($cid, fn($q)=>$q->where('tgh.company_id', $cid))
            ->whereDate('tgh.plan_date', '>=', Carbon::now()->subDays(2)->toDateString())
            ->orderByDesc('tgh.id')
            ->get(['tgh.*', 'tgd.*',
                   DB::raw('tgh.id as plan_header_id'),
                   DB::raw('tgd.id as plan_detail_id')]);

        $grouped = [];
        foreach ($rows as $r) {
            $docId = $r->plan_header_id;
            if (empty($grouped[$docId])) {
                $grouped[$docId] = [
                    't_gi_doc_id'                    => $docId,
                    't_gi_plant'                     => $r->plant_code,
                    't_gi_posting_date'              => $r->plan_date,
                    't_gi_document_date'             => $r->plan_date,
                    't_gi_movement_type'             => $r->movement_type,
                    't_gi_storage_location_from_code'=> $r->sloc_code,
                    't_gi_cost_center'               => $r->cost_center ?? null,
                    't_gi_wbs'                       => $r->wbs_code ?? null,
                    't_gi_doc_type'                  => 'GI',
                    't_gi_material'                  => [],
                ];
            }
            $grouped[$docId]['t_gi_material'][] = [
                'material_stock_id'  => $r->plan_detail_id,
                'material_code'      => $r->material_code,
                'material_name'      => $r->material_name,
                'material_qty'       => $r->qty,
                'material_take_qty'  => $r->qty,
                'material_uom'       => $r->uom,
                'material_plant_code'=> $r->plant_code,
                'material_sloc_code' => $r->sloc_code,
                'material_type_code' => '',
                'material_trans_date'=> $r->created_at,
            ];
        }
        return array_values($grouped);
    }

    private function getGiFinished(?int $cid): array
    {
        $rows = DB::table('tr_gi_detail as tgd')
            ->join('tr_gi_header as tgh', 'tgd.gi_header_id', '=', 'tgh.id')
            ->when($cid, fn($q)=>$q->where('tgh.company_id', $cid))
            ->orderByDesc('tgh.id')
            ->get(['tgh.*', 'tgd.*', 'tgh.id as header_id', 'tgd.id as detail_id']);
        $grouped = [];
        foreach ($rows as $r) {
            $id = $r->header_id;
            if (empty($grouped[$id])) {
                $grouped[$id] = [
                    'm_gi_doc_id'           => (int) $id,
                    'm_gi_number'           => $r->gi_document_number,
                    'm_gi_plant'            => $r->plant_code,
                    'm_gi_posting_date'     => $r->gi_date,
                    'm_gi_movement_type'    => $r->movement_type,
                    'm_gi_storage_location' => $r->sloc_code,
                    'm_gi_status'           => $r->integration_status,
                    'm_gi_doc_type'         => 'GI',
                    'm_gi_material'         => [],
                ];
            }
            $grouped[$id]['m_gi_material'][] = [
                'material_stock_id'  => (int) $r->detail_id,
                'material_code'      => $r->material_code,
                'material_name'      => $r->material_name,
                'material_qty'       => (string) ($r->qty ?? 0),
                'material_take_qty'  => (string) ($r->qty ?? 0),
                'material_uom'       => $r->uom,
                'material_plant_code'=> $r->plant_code,
                'material_sloc_code' => 0,
                'material_type_code' => 0,
                'material_trans_date'=> $r->created_at,
            ];
        }
        return array_values($grouped);
    }

    private function getBlock(string $estate, ?int $cid): array
    {
        $today = Carbon::today()->toDateString();
        return DB::table('m_block')
            ->when($estate, fn($q)=>$q->where('estate_code',$estate))
            ->whereDate('valid_from','<=',$today)->whereDate('valid_to','>=',$today)
            ->orderBy('block_code')
            ->get()
            ->map(fn($r)=>[
                'block_id'          => (int) $r->id,
                'block_code'        => $r->block_code,
                'block_division_code'=> $r->division_code,
                'block_name'        => $r->block_name,
                'block_state'       => $r->block_state,
                'block_is_planted'  => $r->is_planted,
                'block_crop_type'   => $r->crop_type,
            ])->all();
    }

    private function getWorktype(?int $cid): array
    {
        return DB::table('m_worktype')
            ->when($cid, fn($q)=>$q->where('company_id',$cid))
            ->get()
            ->map(fn($r)=>[
                'worktype_id'  => (int) $r->id,
                'worktype_code'=> $r->worktype_code,
                'worktype_name'=> $r->worktype_name,
            ])->all();
    }
}
