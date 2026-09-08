<?php

namespace App\Http\Controllers\Api\V1_1;

use Illuminate\Http\JsonResponse;

/**
 * Base controller for the EPMS Mobile API (v1_1).
 *
 * Replicates the response envelope produced by the legacy CodeIgniter 3
 * REST_Controller so existing mobile clients keep working unchanged. The CI3
 * API returned the payload as-is with the given HTTP status code, and for
 * error paths a body shaped like {"message": "..."} or {"status": ..., "message": ...}.
 *
 * IMPORTANT: The JSON contract is the source of truth (byte-for-byte). Do not
 * reshape success payloads here — controllers build the exact structure the
 * CI3 endpoints produced and pass it straight through.
 */
abstract class ApiController
{
    // HTTP status codes mirroring REST_Controller constants used by the CI3 API.
    public const HTTP_OK                    = 200;
    public const HTTP_BAD_REQUEST           = 400;
    public const HTTP_UNAUTHORIZED          = 401;
    public const HTTP_FORBIDDEN             = 403;
    public const HTTP_NOT_FOUND             = 404;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    /**
     * Send a raw payload with the given status code, exactly like CI3
     * $this->response($data, $code). Payload is emitted as-is (no wrapping),
     * matching the mobile contract.
     */
    protected function respond(mixed $data, int $code = self::HTTP_OK): JsonResponse
    {
        return response()->json($data, $code);
    }

    /** Error body {"message": "..."} used by many CI3 endpoints. */
    protected function respondMessage(string $message, int $code): JsonResponse
    {
        return response()->json(['message' => $message], $code);
    }
}
