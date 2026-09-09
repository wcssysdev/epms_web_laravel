<?php

namespace App\Http\Controllers\Api\V1_1;

use App\Http\Controllers\Api\V1_1\Upload\FieldStaffUpload;
use App\Http\Controllers\Api\V1_1\Upload\HarvestClerkUpload;
use App\Http\Controllers\Api\V1_1\Upload\TransportClerkUpload;
use App\Http\Controllers\Api\V1_1\Upload\CoconutUpload;
use App\Http\Controllers\Api\V1_1\Upload\MillGraderUpload;
use App\Http\Controllers\Api\V1_1\Upload\GiGrUpload;
use App\Models\Transaction\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * EPMS Mobile API — In (upload/sync). Replicates CI3 In::upload_post.
 *
 * POST /api/v1_1/in/upload   (token-protected via api.token)
 * Body: epms_data = JSON string containing per-role-bucket transaction lists.
 *
 * GI/GR (data_t_gi / data_t_gr): dispatched first and returns early,
 * exactly as CI3 exits after save_transaction_datas_gigr().
 * All other plantation buckets are handled inside a DB transaction.
 */
class InController extends ApiController
{
    public function upload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('api_user');

        // Audit raw payload (CI3 res_data).
        DB::table('res_data')->insert([
            'res_text'      => json_encode($request->post()),
            'res_timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $raw  = $request->input('epms_data');
        $data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (! is_array($data)) {
            return $this->respondMessage('Invalid payload', self::HTTP_BAD_REQUEST);
        }

        $companyId = $user->company_id;

        // ── BATCH 3: GI/GR early-return (mirrors CI3 early exit) ──────────────
        if (! empty($data['data_t_gi']) || ! empty($data['data_t_gr'])) {
            $result = (new GiGrUpload($companyId, $user))->handle($data);
            return match ($result) {
                'ok'     => $this->respond('OK', self::HTTP_OK),
                'nodata' => $this->respond('No Data To Be Saved', self::HTTP_NOT_FOUND),
                default  => $this->respond('Failed to save datas', self::HTTP_INTERNAL_SERVER_ERROR),
            };
        }

        // ── BATCH 2b–2e: plantation buckets in one transaction ────────────────
        DB::transaction(function () use ($data, $companyId, $user) {

            // field_staff: attendance + workdone + workdone material
            if (! empty($data['field_staff'])) {
                (new FieldStaffUpload($companyId, $user))->handle($data['field_staff']);
            }

            // harvest_clerk: OPH sawit + persons
            if (! empty($data['harvest_clerk'])) {
                (new HarvestClerkUpload($companyId, $user))->handle($data['harvest_clerk']);
            }

            // transport_clerk: CP + FDN sawit + loaders
            if (! empty($data['transport_clerk'])) {
                $tc = new TransportClerkUpload($companyId, $user);
                $tc->handle($data['transport_clerk']);
                $tc->cpLoadersAll(array_merge(
                    $data['transport_clerk']['T_CP_Loader_Schema_List']   ?? [],
                    $data['transport_clerk']['T_CP_1_Loader_Schema_List'] ?? []
                ));
                $tc->fdnLoadersAll(
                    $data['transport_clerk']['T_FDN_Loader_Schema_List'] ?? []
                );
            }

            // coconut buckets
            $coconut = new CoconutUpload($companyId, $user);
            if (! empty($data['harvest_clerk_coconut'])) {
                $coconut->handleHarvest($data['harvest_clerk_coconut']);
            }
            if (! empty($data['transport_clerk_coconut'])) {
                $tc = $tc ?? new TransportClerkUpload($companyId, $user);
                $coconut->handleTransport($data['transport_clerk_coconut'], $tc);
            }

            // mill_grader + muster_chit_report
            if (! empty($data['mill_grader'])) {
                (new MillGraderUpload($companyId, $user))->handle($data['mill_grader']);
            }
            if (! empty($data['muster_chit_report'])) {
                (new MillGraderUpload($companyId, $user))->handleMusterChit($data['muster_chit_report']);
            }
        });

        return $this->respond(['status' => 'HTTP_OK', 'message' => 'Data successfully saved'], self::HTTP_OK);
    }
}
