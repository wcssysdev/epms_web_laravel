<?php

namespace App\Http\Controllers\Api\V1_1;

use App\Http\Controllers\Api\V1_1\Support\LoginPayload;
use App\Http\Controllers\Api\V1_1\Support\MobileRole;
use App\Models\Transaction\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * EPMS Mobile API — Auth (login). Replicates CI3 Auth::login_post contract.
 *
 * POST /api/v1_1/auth/login
 * Body: user_login, password, is_empty (0|1), imei (optional),
 *       last_login_date + last_login_time (optional).
 */
class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        // 1) Audit the raw request body (CI3 res_data).
        DB::table('res_data')->insert([
            'res_text'      => json_encode($request->post()),
            'res_timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        // 2) Normalise + validate.
        $userLogin = strtolower((string) $request->input('user_login', ''));
        $password  = (string) $request->input('password', '');

        $validator = Validator::make($request->all(), [
            'user_login'   => 'required|max:50',
            'password'     => 'required|max:50',
            'is_empty'     => 'required|in:0,1',
            'imei_divice'  => 'nullable|size:15',
        ]);
        if ($validator->fails()) {
            return $this->respondMessage($validator->errors()->first(), self::HTTP_BAD_REQUEST);
        }

        // 3) Authenticate against tc_user (username/is_active in Laravel schema).
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [$userLogin])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return $this->respondMessage('Invalid Role', self::HTTP_BAD_REQUEST);
        }

        // password column is hidden; fetch raw hash.
        $hash = DB::table('tc_user')->where('id', $user->id)->value('password');
        if (! $hash || ! Hash::check($password, $hash)) {
            return $this->respondMessage('Invalid Credentials', self::HTTP_BAD_REQUEST);
        }

        // System lock gate (CI3 isLocked()).
        if ($user->companyConfig?->is_lock_system) {
            return $this->respondMessage(
                'System Locked, Required Manager Approval to Proceed.!',
                self::HTTP_UNAUTHORIZED
            );
        }

        // 4) Resolve mobile role number from role_code.
        $roleNum = MobileRole::toNumber($user->role_code);

        // 5) Issue an opaque token (CI3 stored a JWT string in user_token; we keep
        //    it opaque for the X-Api-Key guard — no JWT verification is done).
        $token = (string) Str::uuid() . Str::random(24);
        DB::table('tc_user')->where('id', $user->id)->update(['user_token' => $token]);
        $user->refresh();

        // 6) last_login timestamp from client (optional).
        $lastLogin = null;
        if ($request->filled('last_login_date') && $request->filled('last_login_time')) {
            $lastLogin = $request->input('last_login_date') . ' ' . $request->input('last_login_time');
        }

        $isEmpty = (int) $request->input('is_empty') === 1;

        // 7) login_log (device from imei).
        $deviceId = null;
        if ($request->filled('imei')) {
            $deviceId = DB::table('m_devices')->where('device_imei', $request->input('imei'))->value('id');
        }
        $loginId = DB::table('login_log')->insertGetId([
            'company_id'          => $user->company_id,
            'user_id'             => $user->id,
            'login_device_id'     => $deviceId,
            'login_employee_code' => $user->user_employee_code,
            'login_employee_name' => $user->user_name,
            'created_at'          => Carbon::now(),
        ]);

        // 8) Build payload.
        $payload = (new LoginPayload(
            user: $user,
            roleNum: $roleNum,
            username: $userLogin,
            isEmpty: $isEmpty,
            lastLogin: $lastLogin,
            token: $token,
            loginId: $loginId,
        ))->build();

        return $this->respond($payload, self::HTTP_OK);
    }
}
