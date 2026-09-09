<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1_1\PingController;
use App\Http\Controllers\Api\V1_1\AuthController;
use App\Http\Controllers\Api\V1_1\InController;
use App\Http\Controllers\Api\V1_1\GoodsController;
use App\Http\Controllers\Api\V1_1\RampController;
use App\Http\Controllers\Api\V1_1\ExternalController;

/*
|--------------------------------------------------------------------------
| EPMS Mobile API Routes (v1_1)
|--------------------------------------------------------------------------
| Migrated from the legacy CodeIgniter 3 API (api/application/controllers/v1_1).
| The JSON contract is the source of truth (byte-for-byte) so existing mobile
| clients keep working. Endpoints mirror the CI3 URLs, e.g. CI3 POST
| "v1_1/auth/login" -> here POST "/api/v1_1/auth/login".
|
| Auth model (per CI3):
|   - Login endpoints are public (no token yet).
|   - All other endpoints require the "api.token" middleware, which validates
|     the X-Api-Key header against tc_user.user_token.
*/

Route::prefix('v1_1')->group(function () {

    // ── Public wiring probe ────────────────────────────────────────────────
    Route::get('ping', [PingController::class, 'ping']);

    // ── Auth (login) — public, mirrors CI3 POST v1_1/auth/login ──────────────
    Route::post('auth/login', [AuthController::class, 'login']);

    // ── Token-protected endpoints (X-Api-Key) ──────────────────────────────
    Route::middleware('api.token')->group(function () {
        Route::get('whoami', [PingController::class, 'whoami']);

        // Upload/sync (CI3 In::upload_post -> POST v1_1/in/upload).
        Route::post('in/upload', [InController::class, 'upload']);

        // Goods master data for warehouse/store clerk (CI3 Goods::master_post).
        Route::post('goods/master', [GoodsController::class, 'master']);

        // Ramp (CI3 Ramp::get_cp_oph_post + get_cp_non_fdn_post).
        Route::post('ramp/get-cp-oph',     [RampController::class, 'getCpOph']);
        Route::post('ramp/get-cp-non-fdn', [RampController::class, 'getCpNonFdn']);
    });

    // External — no token auth (CI3 External::spb_actual_tonnage_get).
    Route::get('external/spb-actual-tonnage', [ExternalController::class, 'spbActualTonnage']);

});
