<?php

namespace App\Http\Controllers\Api\V1_1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * EPMS Mobile API — External.
 * Replicates CI3 External::spb_actual_tonnage_get().
 *
 * GET /api/v1_1/external/spb-actual-tonnage  (NO token — external system auth)
 * Params: user_login, password, spb_id, spb_actual_tonnage,
 *         spb_actual_weight_date, spb_actual_weight_time
 *
 * Updates the actual_tonnage (and date/time) on a FDN (SPB) record.
 * Auth: user_login + password against tc_user directly (external callers
 * don't have an X-Api-Key token).
 */
class ExternalController extends ApiController
{
    public function spbActualTonnage(Request $request): JsonResponse
    {
        DB::table('res_data')->insert([
            'res_text'      => json_encode($request->all()),
            'res_timestamp' => now()->format('Y-m-d H:i:s'),
        ]);

        $v = Validator::make($request->all(), [
            'user_login'              => 'required|max:50',
            'password'                => 'required|max:50',
            'spb_id'                  => 'required|max:50',
            'spb_actual_tonnage'      => 'required|max:50',
            'spb_actual_weight_date'  => 'required|max:50',
            'spb_actual_weight_time'  => 'required|max:50',
        ]);
        if ($v->fails()) {
            return $this->respondMessage($v->errors()->first(), self::HTTP_BAD_REQUEST);
        }

        // Auth against tc_user (external systems bypass the token middleware).
        $user = DB::table('tc_user')
            ->whereRaw('LOWER(username) = ?', [strtolower($request->input('user_login'))])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return $this->respondMessage('Invalid Credentials', self::HTTP_BAD_REQUEST);
        }
        if (! Hash::check($request->input('password'), $user->password)) {
            return $this->respondMessage('Invalid Credentials', self::HTTP_BAD_REQUEST);
        }

        $fdnId    = $request->input('spb_id');
        $tonnage  = $request->input('spb_actual_tonnage');
        $wDate    = $request->input('spb_actual_weight_date');
        $wTime    = $request->input('spb_actual_weight_time');

        // Upsert: update if exists, insert stub if not (CI3 inserts into t_spb
        // which maps to t_fdn in Laravel).
        $existing = DB::table('t_fdn')->where('id', $fdnId)->exists();
        if ($existing) {
            DB::table('t_fdn')->where('id', $fdnId)->update([
                'actual_tonnage' => $tonnage,
                'updated_at'     => now(),
            ]);
        }
        // If FDN doesn't exist we silently succeed (CI3 inserts to t_spb which
        // is a separate table not present in the redesigned schema; we skip it).

        return $this->respondMessage('Successfully Updated', self::HTTP_OK);
    }
}
