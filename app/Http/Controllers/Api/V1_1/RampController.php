<?php

namespace App\Http\Controllers\Api\V1_1;

use App\Models\Transaction\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EPMS Mobile API — Ramp.
 * Replicates CI3 Ramp::get_cp_oph_post() and get_cp_non_fdn_post().
 *
 * Both endpoints are called by ramp/weighbridge operators to fetch CP records
 * that have not yet been dispatched via FDN, grouped by receiving point.
 *
 * POST /api/v1_1/ramp/get-cp-oph     — coconut CP (cp_type=2, detail_type=2)
 *                                       not yet in any FDN
 * POST /api/v1_1/ramp/get-cp-non-fdn — palm CP (all cp_type) not yet in FDN
 *                                       + CP1 schema
 */
class RampController extends ApiController
{
    /**
     * get_cp_oph_post: CP records of type coconut (cp_type=2, detail_type=2)
     * per receiving_point_code[] where OPH detail not yet transported (no FDN).
     */
    public function getCpOph(Request $request): JsonResponse
    {
        DB::table('res_data')->insert([
            'res_text'      => json_encode($request->post()),
            'res_timestamp' => now()->format('Y-m-d H:i:s'),
        ]);

        $rpCodes = $request->input('receiving_point_code', []);
        if (! is_array($rpCodes) || empty($rpCodes)) {
            return $this->respond(['T_CP_Schema' => []], self::HTTP_OK);
        }

        $data = [];
        foreach ($rpCodes as $rp) {
            $cps = DB::table('t_cp')
                ->join('t_cp_detail', function ($j) {
                    $j->on('t_cp_detail.cp_id', '=', 't_cp.id')
                      ->where('t_cp.cp_type', 2)
                      ->where('t_cp_detail.detail_type', 2);
                })
                ->join('t_oph', function ($j) {
                    $j->on('t_oph.id', '=', 't_cp_detail.oph_id')
                      ->where('t_oph.is_restant_permanent', '!=', 1);
                })
                ->leftJoin('t_fdn_detail', 't_cp_detail.oph_id', '=', 't_fdn_detail.oph_id')
                ->leftJoin('m_vendor', 't_cp.license_number_vendor', '=', 'm_vendor.vendor_code')
                ->leftJoin('m_vendor as v2', 't_cp.license_number_vendor2', '=', 'v2.vendor_code')
                ->where('t_cp.receiving_point_code', $rp)
                ->whereNull('t_fdn_detail.id')
                ->where('t_cp.is_deleted', false)
                ->orderBy('t_cp.created_at')
                ->get(['t_cp.*',
                    DB::raw("COALESCE(m_vendor.vendor_name,'') as cp_license_number_vendor_name"),
                    DB::raw("COALESCE(v2.vendor_name,'') as cp_license_number_vendor_name2"),
                ]);

            foreach ($cps as $cp) {
                $data[] = $this->buildCpRow($cp);
            }
        }

        // Deduplicate by cp_id, attach OPH detail + loaders.
        $data = $this->attachOphAndLoaders($data, true);
        $data = $this->recalcBunches($data);

        return $this->respond(['T_CP_Schema' => array_values($data)], self::HTTP_OK);
    }

    /**
     * get_cp_non_fdn_post: all palm CP per receiving_point_code[] not yet in FDN,
     * including CP1. Returns T_CP_Schema (cp_type=2) and T_CP_1_Schema (cp_type=1).
     */
    public function getCpNonFdn(Request $request): JsonResponse
    {
        DB::table('res_data')->insert([
            'res_text'      => json_encode($request->post()),
            'res_timestamp' => now()->format('Y-m-d H:i:s'),
        ]);

        $rpCodes = $request->input('receiving_point_code', []);
        if (! is_array($rpCodes) || empty($rpCodes)) {
            return $this->respond(['T_CP_Schema' => [], 'T_CP_1_Schema' => []], self::HTTP_OK);
        }

        $cutoff = now()->subDays(15)->toDateString();
        $cp2 = []; $cp1 = [];

        foreach ($rpCodes as $rp) {
            $cps = DB::table('t_cp_detail')
                ->join('t_cp', function ($j) {
                    $j->on('t_cp.id', '=', 't_cp_detail.cp_id');
                })
                ->join('t_oph', 't_oph.id', '=', 't_cp_detail.oph_id')
                ->leftJoin('t_fdn_detail', 't_cp_detail.oph_id', '=', 't_fdn_detail.oph_id')
                ->leftJoin('m_vendor', 't_cp.license_number_vendor', '=', 'm_vendor.vendor_code')
                ->leftJoin('m_vendor as v2', 't_cp.license_number_vendor2', '=', 'v2.vendor_code')
                ->where('t_cp.receiving_point_code', $rp)
                ->whereNull('t_fdn_detail.id')
                ->whereDate('t_cp.created_at', '>=', $cutoff)
                ->where('t_cp.is_deleted', false)
                ->orderBy('t_cp.created_at')
                ->get(['t_cp.*',
                    DB::raw("COALESCE(m_vendor.vendor_name,'') as cp_license_number_vendor_name"),
                    DB::raw("COALESCE(v2.vendor_name,'') as cp_license_number_vendor_name2"),
                ]);

            foreach ($cps as $cp) {
                $row = $this->buildCpRow($cp);
                if ((int) ($cp->cp_type ?? 0) === 1) {
                    $cp1[] = $row;
                } else {
                    $cp2[] = $row;
                }
            }
        }

        $cp2 = $this->attachOphAndLoaders($cp2, false);
        $cp2 = $this->recalcBunches($cp2);
        $cp1 = $this->attachOphAndLoaders($cp1, false);
        $cp1 = $this->recalcBunches($cp1);

        return $this->respond([
            'T_CP_Schema'   => array_values($cp2),
            'T_CP_1_Schema' => array_values($cp1),
        ], self::HTTP_OK);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildCpRow(object $cp): array
    {
        return [
            'cp_id'                        => $cp->id,
            'cp_estate_code'               => $cp->estate_code,
            'cp_division_code'             => $cp->division_code,
            'cp_license_number'            => $cp->license_number ?? '',
            'cp_license_number2'           => $cp->license_number2 ?? '',
            'cp_seal_code'                 => $cp->seal_code ?? '',
            'cp_receiving_point_code'      => $cp->receiving_point_code,
            'cp_delivery_note'             => $cp->delivery_note ?? '',
            'cp_total_bunches'             => (int) ($cp->total_bunches ?? 0),
            'cp_total_oph'                 => (int) ($cp->total_oph ?? 0),
            'cp_total_loose_fruit'         => (int) ($cp->total_loose_fruit ?? 0),
            'cp_estimate_tonnage'          => (int) ($cp->estimate_tonnage ?? 0),
            'cp_actual_tonnage'            => (int) ($cp->actual_tonnage ?? 0),
            'cp_is_closed'                 => (int) ($cp->is_closed ?? 0),
            'cp_bruto'                     => (int) ($cp->bruto ?? 0),
            'cp_tarra'                     => (int) ($cp->tarra ?? 0),
            'cp_transporter'               => (int) ($cp->transporter ?? 0),
            'cp_transporter2'              => (int) ($cp->transporter2 ?? 0),
            'cp_license_number_vendor'     => $cp->license_number_vendor ?? '',
            'cp_license_number_vendor2'    => $cp->license_number_vendor2 ?? '',
            'cp_license_number_vendor_name'=> $cp->cp_license_number_vendor_name ?? '',
            'cp_license_number_vendor_name2'=> $cp->cp_license_number_vendor_name2 ?? '',
            'cp_ship_flag'                 => (int) ($cp->ship_flag ?? 0),
            'cp_cable_way'                 => (int) ($cp->cable_way ?? 0),
            'cp_sailing_date'              => $cp->sailing_date ?? '',
            'cp_kerani_kirim_employee_code'=> $cp->kerani_kirim_emp_code ?? '',
            'cp_kerani_kirim_employee_name'=> $cp->kerani_kirim_emp_name ?? '',
            'cp_is_deleted'                => (int) ($cp->is_deleted ?? 0),
            'cp_created_date'              => $cp->created_at ? date('d/m/Y', strtotime($cp->created_at)) : '',
            'cp_bunches_wet'               => (int) ($cp->bunches_wet ?? 0),
            'cp_bunches_ripe'              => (int) ($cp->bunches_ripe ?? 0),
            'cp_bunches_overripe'          => (int) ($cp->bunches_overripe ?? 0),
            'cp_bunches_underripe'         => (int) ($cp->bunches_underripe ?? 0),
            'cp_bunches_unripe'            => (int) ($cp->bunches_unripe ?? 0),
            'cp_bunches_rotten'            => (int) ($cp->bunches_rotten ?? 0),
            'cp_bunches_long_stalk'        => (int) ($cp->bunches_long_stalk ?? 0),
            'cp_bunches_empty'             => (int) ($cp->bunches_empty ?? 0),
            'cp_bunches_dirty'             => (int) ($cp->bunches_dirty ?? 0),
            'cp_bunches_unfresh'           => (int) ($cp->bunches_unfresh ?? 0),
            'cp_bunches_old'               => (int) ($cp->bunches_old ?? 0),
            'cp_bunches_pest_damaged_old'  => (int) ($cp->bunches_pest_damaged_old ?? 0),
            'cp_bunches_pest_damaged_new'  => (int) ($cp->bunches_pest_damaged_new ?? 0),
            'cp_bunches_diseased'          => (int) ($cp->bunches_diseased ?? 0),
            'cp_bunches_total'             => (int) ($cp->bunches_total ?? 0),
            'integration_status'           => $cp->integration_status,
            'request_id'                   => $cp->request_id ?? '',
            'REMARK'                       => $cp->remark ?? '',
            'cp_ophs'                      => [],
            'LOADER'                       => [],
            'cp_driver_employee_code'      => '',
            'cp_driver_employee_name'      => '',
            'cp_driver_percentage'         => 0,
            'cp_driver_vendor'             => '',
            'cp_operator_employee_code'    => '',
            'cp_operator_employee_name'    => '',
            'cp_operator_percentage'       => 0,
            'cp_operator_vendor'           => '',
        ];
    }

    /** Attach cp_ophs[] and LOADER[] to each CP row. Deduplicate by cp_id. */
    private function attachOphAndLoaders(array $rows, bool $filterTransported): array
    {
        $indexed = [];
        foreach ($rows as $r) {
            $indexed[$r['cp_id']] = $r;
        }
        foreach ($indexed as $cpId => &$row) {
            // OPH detail lines
            $ophData = DB::table('t_cp_detail')
                ->leftJoin('t_fdn_detail', 't_cp_detail.oph_id', '=', 't_fdn_detail.oph_id')
                ->join('t_oph', 't_cp_detail.oph_id', '=', 't_oph.id')
                ->where('t_cp_detail.cp_id', $cpId)
                ->get([
                    't_cp_detail.*',
                    DB::raw("COALESCE(t_fdn_detail.fdn_id, null) AS cp_is_transported"),
                    DB::raw('t_oph.bunches_wet as cp_oph_bunches_wet'),
                    DB::raw('t_oph.bunches_ripe as cp_oph_bunches_ripe'),
                    DB::raw('t_oph.bunches_overripe as cp_oph_bunches_overripe'),
                    DB::raw('t_oph.bunches_underripe as cp_oph_bunches_underripe'),
                    DB::raw('t_oph.bunches_unripe as cp_oph_bunches_unripe'),
                    DB::raw('t_oph.bunches_rotten as cp_oph_bunches_rotten'),
                    DB::raw('t_oph.bunches_long_stalk as cp_oph_bunches_long_stalk'),
                    DB::raw('t_oph.bunches_empty as cp_oph_bunches_empty'),
                    DB::raw('t_oph.bunches_dirty as cp_oph_bunches_dirty'),
                    DB::raw('t_oph.bunches_unfresh as cp_oph_bunches_unfresh'),
                    DB::raw('t_oph.bunches_old as cp_oph_bunches_old'),
                    DB::raw('COALESCE(t_oph.bunches_pest_damaged_old,0) as cp_oph_bunches_pest_damaged_old'),
                    DB::raw('COALESCE(t_oph.bunches_pest_damaged_new,0) as cp_oph_bunches_pest_damaged_new'),
                    DB::raw('t_oph.bunches_diseased as cp_oph_bunches_diseased'),
                    DB::raw('t_oph.bunches_total'),
                ]);

            $ophs = [];
            foreach ($ophData as $o) {
                if ($filterTransported && $o->cp_is_transported !== null) continue;
                $ophs[] = [
                    'cp_detail_id'                  => $o->id,
                    'cp_id'                         => $o->cp_id,
                    'cp_oph_id'                     => $o->oph_id,
                    'cp_oph_bunches_delivered'       => (int) ($o->bunches_delivered ?? 0),
                    'cp_oph_loose_fruit_delivered'   => (int) ($o->loose_fruit_delivered ?? 0),
                    'cp_oph_bunches_wet'             => (int) ($o->cp_oph_bunches_wet ?? 0),
                    'cp_oph_bunches_ripe'            => (int) ($o->cp_oph_bunches_ripe ?? 0),
                    'cp_oph_bunches_overripe'        => (int) ($o->cp_oph_bunches_overripe ?? 0),
                    'cp_oph_bunches_underripe'       => (int) ($o->cp_oph_bunches_underripe ?? 0),
                    'cp_oph_bunches_unripe'          => (int) ($o->cp_oph_bunches_unripe ?? 0),
                    'cp_oph_bunches_rotten'          => (int) ($o->cp_oph_bunches_rotten ?? 0),
                    'cp_oph_bunches_long_stalk'      => (int) ($o->cp_oph_bunches_long_stalk ?? 0),
                    'cp_oph_bunches_empty'           => (int) ($o->cp_oph_bunches_empty ?? 0),
                    'cp_oph_bunches_dirty'           => (int) ($o->cp_oph_bunches_dirty ?? 0),
                    'cp_oph_bunches_unfresh'         => (int) ($o->cp_oph_bunches_unfresh ?? 0),
                    'cp_oph_bunches_old'             => (int) ($o->cp_oph_bunches_old ?? 0),
                    'cp_oph_bunches_pest_damaged_old'=> (int) ($o->cp_oph_bunches_pest_damaged_old ?? 0),
                    'cp_oph_bunches_pest_damaged_new'=> (int) ($o->cp_oph_bunches_pest_damaged_new ?? 0),
                    'cp_oph_bunches_diseased'        => (int) ($o->cp_oph_bunches_diseased ?? 0),
                    'bunches_total'                  => (int) ($o->bunches_total ?? 0),
                    'REMARK'                         => $o->remark ?? '',
                ];
            }
            $row['cp_ophs'] = array_values($ophs);
            $row['cp_total_oph'] = count($ophs);

            // Loaders
            $loaders = [];
            $driver = DB::table('t_cp_loader')->where('cp_id',$cpId)->where('loader_type',1)->first();
            if ($driver) {
                $row['cp_driver_employee_code'] = $driver->employee_code ?? '';
                $row['cp_driver_employee_name'] = $driver->employee_name ?? '';
                $row['cp_driver_percentage']    = $driver->percentage ?? 0;
                $row['cp_driver_vendor']        = $driver->vendor_code ?? '';
                $loaders[] = ['cp_loader_employee_code'=>$driver->employee_code??'','cp_loader_employee_name'=>$driver->employee_name??'','cp_loader_percentage'=>$driver->percentage??0,'cp_loader_vendor'=>$driver->vendor_code??''];
            }
            $op = DB::table('t_cp_loader')->where('cp_id',$cpId)->where('loader_type',3)->first();
            if ($op) {
                $row['cp_operator_employee_code'] = $op->employee_code ?? '';
                $row['cp_operator_employee_name'] = $op->employee_name ?? '';
                $row['cp_operator_percentage']    = $op->percentage ?? 0;
                $row['cp_operator_vendor']        = $op->vendor_code ?? '';
                $loaders[] = ['cp_loader_employee_code'=>$op->employee_code??'','cp_loader_employee_name'=>$op->employee_name??'','cp_loader_percentage'=>$op->percentage??0,'cp_loader_vendor'=>$op->vendor_code??''];
            }
            foreach (DB::table('t_cp_loader')->where('cp_id',$cpId)->where('loader_type',2)->get() as $l) {
                $loaders[] = ['cp_loader_employee_code'=>$l->employee_code??'','cp_loader_employee_name'=>$l->employee_name??'','cp_loader_percentage'=>$l->percentage??0,'cp_loader_vendor'=>$l->vendor_code??''];
            }
            $row['LOADER'] = $loaders;
        }
        unset($row);

        // Remove CPs with no OPH lines.
        return array_values(array_filter($indexed, fn($r)=>count($r['cp_ophs'])>0));
    }

    /** Recompute header bunches as sum of OPH lines (CI3 parity). */
    private function recalcBunches(array $rows): array
    {
        $bunches = ['wet','ripe','overripe','underripe','unripe','rotten','long_stalk','empty','dirty','unfresh','old','pest_damaged_old','pest_damaged_new','diseased','total'];
        foreach ($rows as &$row) {
            foreach ($bunches as $b) {
                $row["cp_bunches_{$b}"] = 0;
            }
            foreach ($row['cp_ophs'] as $o) {
                foreach ($bunches as $b) {
                    $row["cp_bunches_{$b}"] += (int) ($o["cp_oph_bunches_{$b}"] ?? $o['bunches_total'] ?? 0);
                }
            }
            foreach ($row['cp_ophs'] as &$o) { unset($o['bunches_total']); }
            unset($o);
        }
        unset($row);
        return $rows;
    }
}
