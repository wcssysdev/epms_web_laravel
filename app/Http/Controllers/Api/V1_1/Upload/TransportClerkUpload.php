<?php

namespace App\Http\Controllers\Api\V1_1\Upload;

use App\Models\Transaction\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Handles the transport_clerk upload bucket (CI3 In.php transport_clerk branch):
 *   T_CP_Schema_List  (cp_type=2)  |
 *   T_CP_1_Schema_List (cp_type=1) |-> t_cp (upsert by cp_id VARCHAR PK)
 *                                  |   t_cp_detail (insert deduped by cp_id+oph_id)
 *                                  |   t_cp_loader (upsert-deleteWhereNotIn)
 *
 *   T_FDN_Schema_List -> t_fdn (insert new / update if uploaded fdn_line_number
 *                         is greater than stored, keyed by fdn_id VARCHAR PK)
 *                      + t_fdn_detail (insert deduped by fdn_id+oph_id)
 *                      + t_fdn_loader (insert deduped by fdn_id+employee+type)
 *
 * Mobile field names (cp_*, fdn_*) are remapped to the Laravel columns
 * (e.g. cp_kerani_kirim_employee_code -> kerani_kirim_emp_code,
 *  fdn_total_bunches -> total_bunches, fdn_bunches_wet -> fdn_bunches_wet).
 * t_cp.id / t_fdn.id are VARCHAR(100) without auto-default (same as CI3); the
 * mobile-generated ID is used directly.
 */
final class TransportClerkUpload
{
    public function __construct(
        private ?int $companyId,
        private User $user,
    ) {}

    public function handle(array $bucket): void
    {
        $this->cp($bucket['T_CP_Schema_List'] ?? [],  2);   // CP2
        $this->cp($bucket['T_CP_1_Schema_List'] ?? [], 1);  // CP1
        $this->fdn($bucket['T_FDN_Schema_List'] ?? []);
    }

    // ── CHECKPOINT (CP1 / CP2) ───────────────────────────────────────────────
    private function cp(array $rows, int $cpType): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $cpId = Normalizer::str($r, 'cp_id');
            if ($cpId === null) continue;

            $header = $this->mapCp($r, $cpId, $cpType);
            $existing = DB::table('t_cp')->where('id', $cpId)->first();

            if (! $existing) {
                DB::table('t_cp')->insert($header);
            } else {
                $upd = $header;
                unset($upd['id'], $upd['created_at']);
                $upd['updated_at'] = Carbon::now();
                DB::table('t_cp')->where('id', $cpId)->update($upd);
            }

            // Detail lines (sub-array 'cp_detail').
            $this->cpDetail($cpId, $r['cp_detail'] ?? []);

            // Loaders handled by T_CP_Loader_Schema_List / T_CP_1_Loader_Schema_List
            // (processed in cpLoaders() below, keyed by cp_id).
        }

        // Loaders come as a flat top-level list keyed by cp_id.
        $loaderKey = $cpType === 1 ? 'T_CP_1_Loader_Schema_List' : 'T_CP_Loader_Schema_List';
        // The caller bucket is not accessible here; loaders are dispatched
        // separately after all CPs because we need to match by cp_id.
        // The caller handle() passes loaders to cpLoadersAll() below.
    }

    /** Process CP detail lines (sub-array 'cp_detail' within a CP row). */
    private function cpDetail(string $cpId, array $lines): void
    {
        foreach ($lines as $d) {
            if (! is_array($d)) continue;
            $ophId = Normalizer::str($d, 'cp_oph_id');
            if ($ophId === null) continue;

            $exists = DB::table('t_cp_detail')
                ->where('cp_id', $cpId)
                ->where('oph_id', $ophId)
                ->exists();
            if ($exists) continue;

            DB::table('t_cp_detail')->insert([
                'company_id'         => $this->companyId,
                'cp_id'              => $cpId,
                'oph_id'             => $ophId,
                'oph_block_code'     => Normalizer::str($d, 'cp_hc_block_code') ?? Normalizer::str($d, 'cp_oph_block_code') ?? '',
                'oph_tph_code'       => Normalizer::str($d, 'cp_hc_tph_code') ?? Normalizer::str($d, 'cp_oph_tph_code') ?? '',
                'oph_card_id'        => Normalizer::str($d, 'cp_hc_card_id') ?? Normalizer::str($d, 'cp_oph_card_id'),
                'oph_platform_no'    => Normalizer::str($d, 'cp_oph_platform_no'),
                'bunches_delivered'  => Normalizer::intOr0($d, 'cp_oph_bunches_delivered'),
                'loose_fruit_delivered' => Normalizer::floatOr0($d, 'cp_oph_loose_fruit_delivered'),
                'detail_type'        => 0,  // palm detail
                'integration_status' => -1,
            ]);
        }
    }

    /** CP loaders: upsert-deleteWhereNotIn by (cp_id, loader_type). */
    public function cpLoadersAll(array $loaderRows): void
    {
        $byCpId = [];
        foreach ($loaderRows as $l) {
            if (! is_array($l)) continue;
            $cpId = Normalizer::str($l, 'cp_id');
            if ($cpId === null) continue;
            $byCpId[$cpId][] = $l;
        }
        foreach ($byCpId as $cpId => $loaders) {
            $insertedIds = [];
            foreach ($loaders as $l) {
                $empCode = Normalizer::str($l, 'cp_loader_employee_code');
                $ltype   = Normalizer::intOr0($l, 'cp_loader_type');
                $existing = DB::table('t_cp_loader')
                    ->where('cp_id', $cpId)
                    ->where('employee_code', $empCode)
                    ->where('loader_type', $ltype)
                    ->first();
                if ($existing) {
                    DB::table('t_cp_loader')->where('id', $existing->id)->update([
                        'employee_name' => Normalizer::str($l, 'cp_loader_employee_name') ?? '',
                        'vendor_code'   => Normalizer::str($l, 'cp_loader_vendor'),
                        'transporter'   => Normalizer::intOr0($l, 'cp_loader_transporter'),
                        'percentage'    => Normalizer::floatOr0($l, 'cp_loader_percentage'),
                    ]);
                    $insertedIds[] = $existing->id;
                } else {
                    $newId = DB::table('t_cp_loader')->insertGetId([
                        'company_id'    => $this->companyId,
                        'cp_id'         => $cpId,
                        'employee_code' => $empCode,
                        'employee_name' => Normalizer::str($l, 'cp_loader_employee_name') ?? '',
                        'vendor_code'   => Normalizer::str($l, 'cp_loader_vendor'),
                        'transporter'   => Normalizer::intOr0($l, 'cp_loader_transporter'),
                        'percentage'    => Normalizer::floatOr0($l, 'cp_loader_percentage'),
                        'loader_type'   => $ltype,
                        'integration_status' => -1,
                    ]);
                    $insertedIds[] = $newId;
                }
            }
            // Delete loaders for this CP that were NOT in the uploaded set.
            if (! empty($insertedIds)) {
                DB::table('t_cp_loader')
                    ->where('cp_id', $cpId)
                    ->whereNotIn('id', $insertedIds)
                    ->delete();
            }
        }
    }

    private function mapCp(array $r, string $cpId, int $cpType): array
    {
        return [
            'id'                      => $cpId,
            'company_id'              => $this->companyId,
            'cp_type'                 => $cpType,
            'estate_code'             => Normalizer::str($r, 'cp_estate_code'),
            'division_code'           => Normalizer::str($r, 'cp_division_code'),
            'license_number'          => Normalizer::str($r, 'cp_license_number'),
            'license_number2'         => Normalizer::str($r, 'cp_license_number2'),
            'seal_code'               => Normalizer::str($r, 'cp_seal_code'),
            'receiving_point_code'    => Normalizer::str($r, 'cp_receiving_point_code'),
            'delivery_note'           => Normalizer::str($r, 'cp_delivery_note'),
            'kerani_kirim_emp_code'   => Normalizer::str($r, 'cp_kerani_kirim_employee_code'),
            'kerani_kirim_emp_name'   => Normalizer::str($r, 'cp_kerani_kirim_employee_name'),
            'vendor_code'             => Normalizer::str($r, 'cp_vendor_code'),
            'vendor_name'             => Normalizer::str($r, 'cp_vendor_name'),
            'license_number_vendor'   => Normalizer::str($r, 'cp_license_number_vendor'),
            'transporter'             => Normalizer::str($r, 'cp_transporter'),
            'transporter2'            => Normalizer::str($r, 'cp_transporter2'),
            'bruto'                   => Normalizer::floatOr0($r, 'cp_bruto'),
            'tarra'                   => Normalizer::floatOr0($r, 'cp_tarra'),
            'actual_tonnage'          => Normalizer::floatOr0($r, 'cp_actual_tonnage'),
            'total_bunches'           => Normalizer::intOr0($r, 'cp_total_nuts'),   // coconut uses nuts; palm uses bunches from detail sum
            'total_oph'               => Normalizer::intOr0($r, 'cp_total_oph'),
            'total_loose_fruit'       => 0,
            'bin_number'              => Normalizer::str($r, 'cp_bin_number'),
            // Grading bunches (pest_damaged_old/new already present from earlier migration)
            'bunches_wet'             => Normalizer::intOr0($r, 'cp_bunches_wet'),
            'bunches_ripe'            => Normalizer::intOr0($r, 'cp_bunches_ripe'),
            'bunches_overripe'        => Normalizer::intOr0($r, 'cp_bunches_overripe'),
            'bunches_underripe'       => Normalizer::intOr0($r, 'cp_bunches_underripe'),
            'bunches_unripe'          => Normalizer::intOr0($r, 'cp_bunches_unripe'),
            'bunches_rotten'          => Normalizer::intOr0($r, 'cp_bunches_rotten'),
            'bunches_long_stalk'      => Normalizer::intOr0($r, 'cp_bunches_long_stalk'),
            'bunches_empty'           => Normalizer::intOr0($r, 'cp_bunches_empty'),
            'bunches_dirty'           => Normalizer::intOr0($r, 'cp_bunches_dirty'),
            'bunches_unfresh'         => Normalizer::intOr0($r, 'cp_bunches_unfresh'),
            'bunches_old'             => Normalizer::intOr0($r, 'cp_bunches_old'),
            'bunches_pest_damaged_old'=> Normalizer::intOr0($r, 'cp_bunches_pest_damaged_old'),
            'bunches_pest_damaged_new'=> Normalizer::intOr0($r, 'cp_bunches_pest_damaged_new'),
            'bunches_diseased'        => Normalizer::intOr0($r, 'cp_bunches_diseased'),
            'is_closed'               => 0,
            'is_deleted'              => false,
            'integration_status'      => -1,
            'created_by'              => Normalizer::str($r, 'cp_created_by') ?? $this->actor(),
            'updated_by'              => Normalizer::str($r, 'cp_updated_by') ?? $this->actor(),
            'created_at'              => Normalizer::timestamp($r, 'cp_created_date', 'cp_created_time'),
            'updated_at'              => Carbon::now(),
        ];
    }

    // ── FDN ─────────────────────────────────────────────────────────────────
    private function fdn(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $fdnId = Normalizer::str($r, 'fdn_id');
            if ($fdnId === null) continue;

            $header = $this->mapFdn($r, $fdnId);
            $existing = DB::table('t_fdn')->where('id', $fdnId)->first();

            if (! $existing) {
                DB::table('t_fdn')->insert($header);
            } else {
                // CI3 update logic: update if uploaded fdn_line_number > stored.
                $uploadedLineNumber = Normalizer::intOr0($r, 'fdn_line_number');
                $storedLineNumber   = (int) ($existing->fdn_line_number ?? 0);
                if ($uploadedLineNumber > $storedLineNumber) {
                    $upd = $header;
                    unset($upd['id'], $upd['created_at']);
                    $upd['updated_at'] = Carbon::now();
                    DB::table('t_fdn')->where('id', $fdnId)->update($upd);
                }
            }

            $this->fdnDetail($fdnId, $r['fdn_oph_detail'] ?? []);
        }
    }

    private function fdnDetail(string $fdnId, array $lines): void
    {
        foreach ($lines as $d) {
            if (! is_array($d)) continue;
            $ophId = Normalizer::str($d, 'fdn_oph_id');
            if ($ophId === null) continue;

            $exists = DB::table('t_fdn_detail')
                ->where('fdn_id', $fdnId)
                ->where('oph_id', $ophId)
                ->exists();
            if ($exists) continue;

            DB::table('t_fdn_detail')->insert([
                'company_id'             => $this->companyId,
                'fdn_id'                 => $fdnId,
                'oph_id'                 => $ophId,
                'oph_block_code'         => Normalizer::str($d, 'fdn_oph_block_code') ?? '',
                'oph_tph_code'           => Normalizer::str($d, 'fdn_oph_tph_code') ?? '',
                'oph_card_id'            => Normalizer::str($d, 'fdn_oph_card_id'),
                'bunches_delivered'      => Normalizer::intOr0($d, 'fdn_oph_bunches_delivered'),
                'loose_fruit_delivered'  => Normalizer::floatOr0($d, 'fdn_oph_loose_fruit_delivered'),
                'detail_type'            => 0,
                'integration_status'     => -1,
            ]);
        }
    }

    /** FDN loaders: insert deduped by fdn_id + employee_code + loader_type. */
    public function fdnLoadersAll(array $loaderRows): void
    {
        foreach ($loaderRows as $l) {
            if (! is_array($l)) continue;
            $fdnId   = Normalizer::str($l, 'fdn_id');
            $empCode = Normalizer::str($l, 'fdn_loader_employee_code');
            $ltype   = Normalizer::intOr0($l, 'fdn_loader_type');
            if ($fdnId === null || $empCode === null) continue;

            $exists = DB::table('t_fdn_loader')
                ->where('fdn_id', $fdnId)
                ->where('employee_code', $empCode)
                ->where('loader_type', $ltype)
                ->exists();
            if ($exists) continue;

            DB::table('t_fdn_loader')->insert([
                'company_id'    => $this->companyId,
                'fdn_id'        => $fdnId,
                'employee_code' => $empCode,
                'employee_name' => Normalizer::str($l, 'fdn_loader_employee_name') ?? '',
                'vendor_code'   => Normalizer::str($l, 'fdn_loader_vendor'),
                'transporter'   => Normalizer::intOr0($l, 'fdn_loader_transporter'),
                'percentage'    => Normalizer::floatOr0($l, 'fdn_loader_percentage'),
                'loader_type'   => $ltype,
                'integration_status' => -1,
            ]);
        }
    }

    private function mapFdn(array $r, string $fdnId): array
    {
        return [
            'id'                       => $fdnId,
            'company_id'               => $this->companyId,
            'fdn_card_id'              => Normalizer::str($r, 'fdn_card_id'),
            'estate_code'              => Normalizer::str($r, 'fdn_estate_code'),
            'division_code'            => Normalizer::str($r, 'fdn_division_code'),
            'license_number'           => Normalizer::str($r, 'fdn_license_number'),
            'license_number2'          => Normalizer::str($r, 'fdn_license_number2'),
            'seal_code'                => Normalizer::str($r, 'fdn_seal_code'),
            'deliver_to_code'          => Normalizer::str($r, 'fdn_deliver_to_code'),
            'deliver_to_name'          => Normalizer::str($r, 'fdn_deliver_to_name'),
            'delivery_note'            => Normalizer::str($r, 'fdn_delivery_note'),
            'kerani_kirim_emp_code'    => Normalizer::str($r, 'fdn_kerani_kirim_employee_code'),
            'kerani_kirim_emp_name'    => Normalizer::str($r, 'fdn_kerani_kirim_employee_name'),
            'vendor_code'              => Normalizer::str($r, 'fdn_vendor_code'),
            'vendor_name'              => Normalizer::str($r, 'fdn_vendor_name'),
            'license_number_vendor'    => Normalizer::str($r, 'fdn_license_number_vendor'),
            'license_number_vendor2'   => Normalizer::str($r, 'fdn_license_number_vendor2'),
            'transporter'              => Normalizer::intOr0($r, 'fdn_transporter'),
            'transporter2'             => Normalizer::intOr0($r, 'fdn_transporter2'),
            'total_bunches'            => Normalizer::intOr0($r, 'fdn_total_bunches'),
            'total_oph'                => Normalizer::intOr0($r, 'fdn_total_oph'),
            'total_loose_fruit'        => Normalizer::intOr0($r, 'fdn_total_loose_fruit'),
            'estimate_tonnage'         => Normalizer::floatOr0($r, 'fdn_estimate_tonnage'),
            'actual_tonnage'           => Normalizer::floatOr0($r, 'fdn_actual_tonnage'),
            'bruto'                    => Normalizer::floatOr0($r, 'fdn_bruto'),
            'tarra'                    => Normalizer::floatOr0($r, 'fdn_tarra'),
            'fdn_write_off'            => Normalizer::floatOr0($r, 'fdn_write_off'),
            'fdn_line_number'          => Normalizer::intOr0($r, 'fdn_line_number'),
            'company_code'             => Normalizer::str($r, 'company_code'),
            // Grading bunches
            'fdn_bunches_wet'          => Normalizer::intOr0($r, 'fdn_bunches_wet'),
            'fdn_bunches_ripe'         => Normalizer::intOr0($r, 'fdn_bunches_ripe'),
            'fdn_bunches_overripe'     => Normalizer::intOr0($r, 'fdn_bunches_overripe'),
            'fdn_bunches_underripe'    => Normalizer::intOr0($r, 'fdn_bunches_underripe'),
            'fdn_bunches_unripe'       => Normalizer::intOr0($r, 'fdn_bunches_unripe'),
            'fdn_bunches_rotten'       => Normalizer::intOr0($r, 'fdn_bunches_rotten'),
            'fdn_bunches_long_stalk'   => Normalizer::intOr0($r, 'fdn_bunches_long_stalk'),
            'fdn_bunches_empty'        => Normalizer::intOr0($r, 'fdn_bunches_empty'),
            'fdn_bunches_dirty'        => Normalizer::intOr0($r, 'fdn_bunches_dirty'),
            'fdn_bunches_unfresh'      => Normalizer::intOr0($r, 'fdn_bunches_unfresh'),
            'fdn_bunches_old'          => Normalizer::intOr0($r, 'fdn_bunches_old'),
            'fdn_bunches_pest_damaged_old' => Normalizer::intOr0($r, 'fdn_bunches_pest_damaged_old'),
            'fdn_bunches_pest_damaged_new' => Normalizer::intOr0($r, 'fdn_bunches_pest_damaged_new'),
            'fdn_bunches_diseased'     => Normalizer::intOr0($r, 'fdn_bunches_diseased'),
            'is_closed'                => 0,
            'is_deleted'               => false,
            'integration_status'       => -1,
            'created_by'               => Normalizer::str($r, 'fdn_created_by') ?? $this->actor(),
            'updated_by'               => Normalizer::str($r, 'fdn_updated_by') ?? $this->actor(),
            'created_at'               => Normalizer::timestamp($r, 'fdn_created_date', 'fdn_created_time'),
            'updated_at'               => Carbon::now(),
        ];
    }

    private function actor(): string
    {
        return (string) ($this->user->user_employee_code ?: $this->user->user_name ?: $this->user->id);
    }
}
