<?php

namespace App\Http\Controllers\Api\V1_1\Upload;

use App\Models\Transaction\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Handles the harvest_clerk upload bucket (CI3 In.php harvest_clerk branch):
 *   - T_OPH_Schema_List        -> t_oph (insert new / update if the uploaded
 *                                 record is newer than the stored one, keyed by oph_id)
 *   - T_OPH_Person_Schema_List -> t_oph_persons (insert if not already present)
 *
 * Mobile field names (oph_*, bunches_*) are remapped to the Laravel columns.
 * t_oph.id is an application-generated VARCHAR PK sourced from mobile oph_id.
 * Photo base64 handling and t_harvesting_deduction are intentionally skipped
 * (the CI3 deduction path is commented out; photos are mobile-capture only).
 */
final class HarvestClerkUpload
{
    public function __construct(
        private ?int $companyId,
        private User $user,
    ) {}

    public function handle(array $bucket): void
    {
        $this->oph($bucket['T_OPH_Schema_List'] ?? []);
        $this->ophPersons($bucket['T_OPH_Person_Schema_List'] ?? []);
    }

    /** t_oph upsert keyed by oph_id (id). Update only when uploaded is newer. */
    private function oph(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;

            $ophId = Normalizer::str($r, 'oph_id');
            if ($ophId === null) continue;

            $createdAt = Normalizer::timestamp($r, 'oph_created_date', 'oph_created_time');
            $payload = $this->mapOph($r, $ophId, $createdAt);

            $existing = DB::table('t_oph')->where('id', $ophId)->first();

            if (! $existing) {
                DB::table('t_oph')->insert($payload);
                continue;
            }

            // Update only if the uploaded record is newer (CI3: created timestamp compare).
            $uploadedNewer = true;
            if ($existing->created_at !== null) {
                try {
                    $uploadedNewer = Carbon::parse($createdAt)->greaterThanOrEqualTo(Carbon::parse($existing->created_at));
                } catch (\Throwable $e) {
                    $uploadedNewer = true;
                }
            }
            if ($uploadedNewer) {
                // Do not overwrite the PK / created_at on update.
                unset($payload['id'], $payload['created_at']);
                $payload['updated_at'] = Carbon::now();
                DB::table('t_oph')->where('id', $ophId)->update($payload);
            }
        }
    }

    /** Map a mobile OPH row to the Laravel t_oph columns. */
    private function mapOph(array $r, string $ophId, string $createdAt): array
    {
        return [
            'id'                       => $ophId,
            'company_id'               => $this->companyId,
            'oph_card_id'              => Normalizer::str($r, 'oph_card_id'),
            'harvest_method'           => Normalizer::str($r, 'oph_harvesting_method'),
            'oph_deduction_indicator'  => Normalizer::intOr0($r, 'oph_deduction_indicator'),
            'estate_code'              => Normalizer::str($r, 'oph_estate_code'),
            'plant_code'               => Normalizer::str($r, 'oph_plant_code'),
            'division_code'            => Normalizer::str($r, 'oph_division_code'),
            'block_code'               => Normalizer::str($r, 'oph_block_code'),
            'tph_code'                 => Normalizer::str($r, 'oph_tph_code'),
            'platform_no'              => Normalizer::str($r, 'oph_platform_no'),
            'lat'                      => Normalizer::str($r, 'oph_lat'),
            'long'                     => Normalizer::str($r, 'oph_long'),
            'notes'                    => Normalizer::str($r, 'oph_notes'),
            'mandor_employee_code'     => Normalizer::str($r, 'mandor_employee_code'),
            'mandor_employee_name'     => Normalizer::str($r, 'mandor_employee_name'),
            'kerani_panen_employee_code' => Normalizer::str($r, 'kerani_panen_employee_code'),
            'kerani_panen_employee_name' => Normalizer::str($r, 'kerani_panen_employee_name'),
            'bunches_wet'              => Normalizer::intOr0($r, 'bunches_wet'),
            'bunches_ripe'             => Normalizer::intOr0($r, 'bunches_ripe'),
            'bunches_overripe'         => Normalizer::intOr0($r, 'bunches_overripe'),
            'bunches_underripe'        => Normalizer::intOr0($r, 'bunches_underripe'),
            'bunches_unripe'           => Normalizer::intOr0($r, 'bunches_unripe'),
            'bunches_rotten'           => Normalizer::intOr0($r, 'bunches_rotten'),
            'bunches_long_stalk'       => Normalizer::intOr0($r, 'bunches_long_stalk'),
            'bunches_empty'            => Normalizer::intOr0($r, 'bunches_empty'),
            'bunches_dirty'            => Normalizer::intOr0($r, 'bunches_dirty'),
            'bunches_unfresh'          => Normalizer::intOr0($r, 'bunches_unfresh'),
            'bunches_old'              => Normalizer::intOr0($r, 'bunches_old'),
            'bunches_pest_damaged_old' => Normalizer::intOr0($r, 'bunches_pest_damaged_old'),
            'bunches_pest_damaged_new' => Normalizer::intOr0($r, 'bunches_pest_damaged_new'),
            'bunches_diseased'         => Normalizer::intOr0($r, 'bunches_diseased'),
            'bunches_total'            => Normalizer::intOr0($r, 'bunches_total'),
            'bunches_not_sent'         => Normalizer::intOr0($r, 'bunches_not_sent'),
            'loose_fruits'             => Normalizer::intOr0($r, 'loose_fruits'),
            'is_planned'               => Normalizer::intOr0($r, 'is_planned'),
            'is_approved'              => Normalizer::intOr0($r, 'is_approved'),
            'is_restant_permanent'     => Normalizer::intOr0($r, 'is_restant_permanent'),
            'approved_by'              => Normalizer::str($r, 'oph_approved_by'),
            'approved_by_name'         => Normalizer::str($r, 'oph_approved_by_name'),
            'approved_at'              => Normalizer::nn($r['oph_approved_date'] ?? null)
                                            ? Normalizer::timestamp($r, 'oph_approved_date', 'oph_approved_time')
                                            : null,
            'is_closed'                => 0,
            'created_by'               => Normalizer::str($r, 'oph_created_by') ?? $this->actor(),
            'updated_by'               => Normalizer::str($r, 'oph_updated_by') ?? $this->actor(),
            'created_at'               => $createdAt,
            'updated_at'               => Carbon::now(),
        ];
    }

    /** Fallback actor identity for created_by/updated_by (NOT NULL columns). */
    private function actor(): string
    {
        return (string) ($this->user->user_employee_code ?: $this->user->user_name ?: $this->user->id);
    }

    /** t_oph_persons: insert if the (oph_id, employee, type) combo is not present. */
    private function ophPersons(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;

            $ophId    = Normalizer::str($r, 'oph_id');
            $empCode  = Normalizer::str($r, 'oph_person_employee_code');
            $type     = Normalizer::intOr0($r, 'oph_person_type');
            if ($ophId === null || $empCode === null) continue;

            $exists = DB::table('t_oph_persons')
                ->where('oph_id', $ophId)
                ->where('employee_code', $empCode)
                ->where('person_type', $type)
                ->exists();
            if ($exists) continue;

            DB::table('t_oph_persons')->insert([
                'company_id'    => $this->companyId,
                'oph_id'        => $ophId,
                'employee_code' => $empCode,
                'employee_name' => Normalizer::str($r, 'oph_person_employee_name'),
                'percentage'    => Normalizer::floatOr0($r, 'oph_person_percentage'),
                'person_type'   => $type,
                'employee_type' => Normalizer::intOr0($r, 'oph_person_employee_type'),
            ]);
        }
    }
}
