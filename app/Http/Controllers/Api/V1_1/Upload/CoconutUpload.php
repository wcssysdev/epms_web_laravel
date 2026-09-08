<?php

namespace App\Http\Controllers\Api\V1_1\Upload;

use App\Models\Transaction\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Handles the coconut upload buckets (CI3 In.php harvest_clerk_coconut and
 * transport_clerk_coconut branches):
 *
 *  harvest_clerk_coconut:
 *    T_Coconut_OPH_Schema_List       -> t_coconut_oph (upsert by id VARCHAR)
 *    T_Coconut_OPH_Person_Schema_List-> t_coconut_oph_persons (dedup)
 *    T_Coconut_OPH_Detail_Schema_List-> t_coconut_oph_detail (insert)
 *
 *  transport_clerk_coconut:
 *    T_CP_Coconut_Schema_List        -> t_cp (cp_type=2) + t_cp_loader (reuses
 *                                       palm CP logic — CI3 uses t_checkpoint same table)
 *    T_FDN_Coconut_Schema_List       -> t_coconut_fdn (upsert by id VARCHAR)
 *    T_FDN_Coconut_Detail_Schema_List-> t_coconut_fdn_detail (dedup)
 *
 * t_coconut_oph.id is VARCHAR(100) NULL-default (application-generated).
 * t_coconut_fdn.id is VARCHAR(100) NULL-default.
 * Many t_coconut_oph columns are NOT NULL — we use '' as fallback for string
 * NOT NULL fields and the authenticated user for created_by.
 */
final class CoconutUpload
{
    public function __construct(
        private ?int $companyId,
        private User $user,
    ) {}

    // ── harvest_clerk_coconut bucket ─────────────────────────────────────────
    public function handleHarvest(array $bucket): void
    {
        $this->coconutOph($bucket['T_Coconut_OPH_Schema_List'] ?? []);
        $this->coconutOphPersons($bucket['T_Coconut_OPH_Person_Schema_List'] ?? []);
        $this->coconutOphDetail($bucket['T_Coconut_OPH_Detail_Schema_List'] ?? []);
    }

    // ── transport_clerk_coconut bucket ───────────────────────────────────────
    public function handleTransport(array $bucket, TransportClerkUpload $tc): void
    {
        // CP coconut reuses t_cp (cp_type=2) same as palm — delegate to TransportClerk.
        if (! empty($bucket['T_CP_Coconut_Schema_List'])) {
            $tc->cpCoconut($bucket['T_CP_Coconut_Schema_List']);
        }
        $tc->cpLoadersAll(array_merge(
            $bucket['T_CP_Coconut_Loader_Schema_List'] ?? [],
            $bucket['T_CP_Coconut_1_Loader_Schema_List'] ?? []
        ));

        $this->coconutFdn($bucket['T_FDN_Coconut_Schema_List'] ?? []);
        $this->coconutFdnDetail($bucket['T_FDN_Coconut_Detail_Schema_List']
            ?? $bucket['T_Coconut_OPH_Detail_Schema_List'] ?? []);
    }

    // ── t_coconut_oph ─────────────────────────────────────────────────────────
    private function coconutOph(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $id = Normalizer::str($r, 'coconut_oph_id');
            if ($id === null) continue;

            $createdAt = Normalizer::timestamp($r, 'coconut_oph_created_date', 'coconut_oph_created_time');
            $payload = [
                'id'                    => $id,
                'company_id'            => $this->companyId,
                'plant_code'            => Normalizer::str($r, 'coconut_oph_plant_code') ?? '',
                'estate_code'           => Normalizer::str($r, 'coconut_oph_estate_code') ?? '',
                'division_code'         => Normalizer::str($r, 'coconut_oph_division_code') ?? '',
                'block_code'            => Normalizer::str($r, 'coconut_oph_block_code') ?? '',
                'tph_code'              => Normalizer::str($r, 'coconut_oph_tph_code') ?? '',
                'oph_card_id'           => Normalizer::str($r, 'coconut_oph_card_id'),
                'gang_code'             => Normalizer::str($r, 'coconut_oph_gang_code'),
                'gang_name'             => Normalizer::str($r, 'coconut_oph_gang_name'),
                'checker_employee_code' => Normalizer::str($r, 'coconut_oph_checker_employee_code') ?? $this->actor(),
                'checker_employee_name' => Normalizer::str($r, 'coconut_oph_checker_employee_name'),
                'notes'                 => Normalizer::str($r, 'coconut_oph_notes'),
                'lat'                   => Normalizer::str($r, 'coconut_oph_lat'),
                'long'                  => Normalizer::str($r, 'coconut_oph_long'),
                'nuts_total'            => Normalizer::intOr0($r, 'coconut_oph_nuts_total'),
                'is_planned'            => Normalizer::intOr0($r, 'coconut_oph_is_planned'),
                'is_approved'           => Normalizer::intOr0($r, 'coconut_oph_is_approved'),
                'approved_by'           => Normalizer::str($r, 'coconut_oph_approved_by'),
                'approved_by_name'      => Normalizer::str($r, 'coconut_oph_approved_by_name'),
                'approved_at'           => Normalizer::nn($r['coconut_oph_approved_timestamp'] ?? null)
                                            ? Normalizer::timestamp($r, 'coconut_oph_approved_timestamp', '')
                                            : null,
                'is_closed'             => 0,
                'is_deleted'            => false,
                'integration_status'    => -1,
                'created_by'            => Normalizer::str($r, 'coconut_oph_created_by') ?? $this->actor(),
                'updated_by'            => Normalizer::str($r, 'coconut_oph_updated_by') ?? $this->actor(),
                'created_at'            => $createdAt,
                'updated_at'            => Carbon::now(),
            ];

            $existing = DB::table('t_coconut_oph')->where('id', $id)->first();
            if (! $existing) {
                DB::table('t_coconut_oph')->insert($payload);
            } else {
                try {
                    $uploadedNewer = Carbon::parse($createdAt)->greaterThanOrEqualTo(Carbon::parse($existing->created_at));
                } catch (\Throwable $e) {
                    $uploadedNewer = true;
                }
                if ($uploadedNewer) {
                    $upd = $payload;
                    unset($upd['id'], $upd['created_at']);
                    $upd['updated_at'] = Carbon::now();
                    DB::table('t_coconut_oph')->where('id', $id)->update($upd);
                }
            }
        }
    }

    /** t_coconut_oph_persons: dedup by coconut_oph_id + employee_code. */
    private function coconutOphPersons(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $ophId   = Normalizer::str($r, 'coconut_oph_id');
            $empCode = Normalizer::str($r, 'coconut_oph_person_employee_code');
            if ($ophId === null || $empCode === null) continue;

            $exists = DB::table('t_coconut_oph_persons')
                ->where('coconut_oph_id', $ophId)
                ->where('employee_code', $empCode)
                ->exists();
            if ($exists) continue;

            DB::table('t_coconut_oph_persons')->insert([
                'company_id'      => $this->companyId,
                'coconut_oph_id'  => $ophId,
                'employee_code'   => $empCode,
                'employee_name'   => Normalizer::str($r, 'coconut_oph_person_employee_name'),
                'activity_type'   => Normalizer::str($r, 'coconut_oph_person_activity_type'),
                'integration_status' => -1,
            ]);
        }
    }

    /** t_coconut_oph_detail: insert rows with material_name resolved if missing. */
    private function coconutOphDetail(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $ophId   = Normalizer::str($r, 'coconut_oph_id');
            $matCode = Normalizer::str($r, 'coconut_oph_detail_material_code');
            if ($ophId === null || $matCode === null) continue;

            $matName = Normalizer::str($r, 'coconut_oph_detail_material_name')
                ?? DB::table('m_coconut_material')->where('material_code', $matCode)->value('material_desc')
                ?? $matCode;

            DB::table('t_coconut_oph_detail')->insert([
                'company_id'        => $this->companyId,
                'coconut_oph_id'    => $ophId,
                'material_code'     => $matCode,
                'material_name'     => $matName,
                'customer_nut_qty'  => Normalizer::floatOr0($r, 'coconut_oph_detail_customer_nut_qty'),
                'is_locked'         => false,
                'is_deleted'        => false,
                'integration_status'=> -1,
            ]);
        }
    }

    // ── t_coconut_fdn ─────────────────────────────────────────────────────────
    private function coconutFdn(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $id = Normalizer::str($r, 'coconut_fdn_id');
            if ($id === null) continue;

            $payload = [
                'id'                    => $id,
                'company_id'            => $this->companyId,
                'estate_code'           => Normalizer::str($r, 'coconut_fdn_estate_code'),
                'division_code'         => Normalizer::str($r, 'coconut_fdn_division_code'),
                'sales_order_no'        => Normalizer::str($r, 'coconut_fdn_sales_order'),
                'sales_order_item'      => Normalizer::str($r, 'coconut_fdn_sales_order_item_code'),
                'receiving_point_code'  => Normalizer::str($r, 'coconut_fdn_receiving_point_code'),
                'license_number'        => Normalizer::str($r, 'coconut_fdn_license_number'),
                'driver_name'           => Normalizer::str($r, 'coconut_fdn_driver_name'),
                'vehicle_vendor_code'   => Normalizer::str($r, 'coconut_fdn_vehicle_vendor_code'),
                'kerani_kirim_emp_code' => Normalizer::str($r, 'coconut_fdn_kerani_kirim_employee_code'),
                'kerani_kirim_emp_name' => Normalizer::str($r, 'coconut_fdn_kerani_kirim_employee_name'),
                'delivery_note'         => Normalizer::str($r, 'coconut_fdn_delivery_note'),
                'fdn_card_id'           => Normalizer::str($r, 'coconut_fdn_card_id'),
                'lat'                   => Normalizer::str($r, 'coconut_fdn_lat'),
                'long'                  => Normalizer::str($r, 'coconut_fdn_long'),
                'total_oph'             => Normalizer::intOr0($r, 'coconut_fdn_total_oph'),
                'bruto'                 => Normalizer::floatOr0($r, 'coconut_fdn_bruto'),
                'tarra'                 => Normalizer::floatOr0($r, 'coconut_fdn_tarra'),
                'actual_tonnage'        => Normalizer::floatOr0($r, 'coconut_fdn_actual_tonnage'),
                'total_customer_qty'    => Normalizer::floatOr0($r, 'coconut_fdn_total_customer_qty'),
                'destination'           => Normalizer::str($r, 'coconut_fdn_destination'),
                'is_nursery'            => Normalizer::intOr0($r, 'coconut_fdn_is_nursery'),
                'is_stock'              => Normalizer::intOr0($r, 'coconut_fdn_is_stock'),
                'is_closed'             => 0,
                'is_deleted'            => false,
                'integration_status'    => -1,
                'created_by'            => Normalizer::str($r, 'coconut_fdn_created_by') ?? $this->actor(),
                'updated_by'            => Normalizer::str($r, 'coconut_fdn_updated_by') ?? $this->actor(),
                'created_at'            => Normalizer::timestamp($r, 'coconut_fdn_created_date', 'coconut_fdn_created_time'),
                'updated_at'            => Carbon::now(),
            ];

            $existing = DB::table('t_coconut_fdn')->where('id', $id)->first();
            if (! $existing) {
                DB::table('t_coconut_fdn')->insert($payload);
            } else {
                $upd = $payload;
                unset($upd['id'], $upd['created_at']);
                $upd['updated_at'] = Carbon::now();
                DB::table('t_coconut_fdn')->where('id', $id)->update($upd);
            }
        }
    }

    /** t_coconut_fdn_detail: dedup by coconut_fdn_id + coconut_oph_id. */
    private function coconutFdnDetail(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $fdnId = Normalizer::str($r, 'coconut_fdn_id');
            $ophId = Normalizer::str($r, 'coconut_fdn_oph_id');
            if ($fdnId === null || $ophId === null) continue;

            $exists = DB::table('t_coconut_fdn_detail')
                ->where('coconut_fdn_id', $fdnId)
                ->where('coconut_oph_id', $ophId)
                ->exists();
            if ($exists) continue;

            DB::table('t_coconut_fdn_detail')->insert([
                'company_id'               => $this->companyId,
                'coconut_fdn_id'           => $fdnId,
                'coconut_oph_id'           => $ophId,
                'coconut_oph_card_id'      => Normalizer::str($r, 'coconut_fdn_oph_card_id'),
                'total_customer_nut_qty'   => Normalizer::floatOr0($r, 'coconut_fdn_oph_total_customer_nut_qty'),
                'integration_status'       => -1,
            ]);
        }
    }

    private function actor(): string
    {
        return (string) ($this->user->user_employee_code ?: $this->user->user_name ?: $this->user->id);
    }
}
