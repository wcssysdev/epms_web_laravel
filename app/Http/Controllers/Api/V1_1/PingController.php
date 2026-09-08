<?php

namespace App\Http\Controllers\Api\V1_1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wiring/health probe for the mobile API. Not part of the CI3 contract — used
 * only to verify routing, base controller, and the token middleware.
 */
class PingController extends ApiController
{
    /** Public: confirms the API prefix + JSON envelope work. */
    public function ping(): JsonResponse
    {
        return $this->respond([
            'status'    => 'OK',
            'service'   => 'epms-mobile-api',
            'version'   => 'v1_1',
            'timestamp' => now()->toDateTimeString(),
        ], self::HTTP_OK);
    }

    /** Protected: confirms the X-Api-Key token guard resolves a tc_user. */
    public function whoami(Request $request): JsonResponse
    {
        $user = $request->attributes->get('api_user');
        return $this->respond([
            'status'    => 'OK',
            'user_id'   => (int) $user->id,
            'user_name' => $user->user_name,
            'role_code' => $user->role_code,
        ], self::HTTP_OK);
    }
}
