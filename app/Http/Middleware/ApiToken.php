<?php

namespace App\Http\Middleware;

use App\Models\Transaction\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EPMS Mobile API token guard — replicates the legacy CI3 recheck_credentials():
 * the client sends its opaque token in the "X-Api-Key" header, and it must match
 * the user_token stored on tc_user for the identified user.
 *
 * The CI3 helper compared the raw token string against tc_user.user_token (the
 * JWT-decode branch was commented out), so the token is effectively an opaque
 * bearer. We keep that behaviour for mobile compatibility.
 *
 * User identity is taken from the token itself: we look up the tc_user row whose
 * user_token equals the presented key. On success the resolved User is bound to
 * the request (attribute "api_user") and logged in on the request for the
 * duration of the call.
 */
class ApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Api-Key');

        if (! $token) {
            return response()->json(['message' => 'Token not valid'], 403);
        }

        $user = User::query()->where('user_token', $token)->first();

        if (! $user) {
            return response()->json(['message' => 'Token not valid'], 403);
        }

        // Bind the authenticated mobile user for downstream controllers.
        $request->attributes->set('api_user', $user);
        auth()->setUser($user);

        return $next($request);
    }
}
