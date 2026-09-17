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
| Case-insensitive aliases (Auth/login vs auth/login) are provided to match
| mobile client HTTP contracts.
*/

Route::prefix('v1_1')->group(function () {

    // Public wiring probe
    Route::get('ping', [PingController::class, 'ping']);

    // Auth (login) — supports both Auth/login and auth/login
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('Auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('Auth/logout', [AuthController::class, 'logout']);

    // Token-protected endpoints (X-Api-Key)
    Route::middleware('api.token')->group(function () {
        Route::get('whoami', [PingController::class, 'whoami']);

        // Upload/sync
        Route::post('in/upload', [InController::class, 'upload']);
        Route::post('In/upload', [InController::class, 'upload']);

        // Goods master data for warehouse/store clerk
        Route::post('goods/master', [GoodsController::class, 'master']);
        Route::post('Goods/master', [GoodsController::class, 'master']);

        // Ramp (both underscore and dash, both cases)
        Route::post('ramp/get-cp-oph',     [RampController::class, 'getCpOph']);
        Route::post('ramp/get_cp_oph',     [RampController::class, 'getCpOph']);
        Route::post('Ramp/get-cp-oph',     [RampController::class, 'getCpOph']);
        Route::post('Ramp/get_cp_oph',     [RampController::class, 'getCpOph']);

        Route::post('ramp/get-cp-non-fdn', [RampController::class, 'getCpNonFdn']);
        Route::post('ramp/get_cp_non_fdn', [RampController::class, 'getCpNonFdn']);
        Route::post('Ramp/get-cp-non-fdn', [RampController::class, 'getCpNonFdn']);
        Route::post('Ramp/get_cp_non_fdn', [RampController::class, 'getCpNonFdn']);
    });

    // External — no token auth
    Route::get('external/spb-actual-tonnage', [ExternalController::class, 'spbActualTonnage']);
});