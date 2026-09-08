<?php

namespace App\Http\Controllers\Api\V1_1;

use App\Http\Controllers\Api\V1_1\Upload\FieldStaffUpload;
use App\Http\Controllers\Api\V1_1\Upload\HarvestClerkUpload;
use App\Http\Controllers\Api\V1_1\Upload\TransportClerkUpload;
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
 * The controller audits the raw body (res_data), decodes epms_data, and
 * dispatches each present role bucket to a dedicated Upload handler. Handlers
 * are added per batch (2b field_staff, 2c harvest_clerk, 2d transport_clerk,
 * 2e coconut/mill_grader).
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

        $raw = $request->input('epms_data');
        $data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (! is_array($data)) {
            return $this->respondMessage('Invalid payload', self::HTTP_BAD_REQUEST);
        }

        $companyId = $user->company_id;

        DB::transaction(function () use ($data, $companyId, $user) {
            // BATCH 2b — field_staff bucket (attendance + workdone + material).
            if (! empty($data['field_staff'])) {
                (new FieldStaffUpload($companyId, $user))->handle($data['field_staff']);
            }
            // BATCH 2c — harvest_clerk bucket (OPH sawit + persons).
            if (! empty($data['harvest_clerk'])) {
                (new HarvestClerkUpload($companyId, $user))->handle($data['harvest_clerk']);
            }
            // BATCH 2d — transport_clerk bucket (CP + FDN sawit + loaders).
            if (! empty($data['transport_clerk'])) {
                $tc = new TransportClerkUpload($companyId, $user);
                $tc->handle($data['transport_clerk']);
                // Loaders are flat top-level lists alongside the CP/FDN lists.
                $tc->cpLoadersAll(array_merge(
                    $data['transport_clerk']['T_CP_Loader_Schema_List']   ?? [],
                    $data['transport_clerk']['T_CP_1_Loader_Schema_List'] ?? []
                ));
                $tc->fdnLoadersAll(
                    $data['transport_clerk']['T_FDN_Loader_Schema_List'] ?? []
                );
            }
            // Further buckets (coconut, mill_grader) dispatched in later batches.
        });

        return $this->respond(['status' => 'HTTP_OK', 'message' => 'Data successfully saved'], self::HTTP_OK);
    }
}
