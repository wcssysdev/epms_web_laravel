<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check that the authenticated user has a role level
 * less than or equal to the required level, OR matches one of the
 * explicitly allowed role codes (useful for cross-cutting roles like IT Staff).
 *
 * Usage in routes:
 *   ->middleware('role:30')            // Company Admin and above
 *   ->middleware('role:20')            // Country Admin and above
 *   ->middleware('role:10')            // Super Admin only
 *   ->middleware('role:40,it_staff')   // level <= 40 OR role_code == it_staff
 */
class CheckRoleLevel
{
    /**
     * @param  int|string  $level         Required role level (lower = more privilege)
     * @param  string      ...$allowedRoleCodes  Extra role codes granted access regardless of level
     */
    public function handle(Request $request, Closure $next, int|string $level = 70, string ...$allowedRoleCodes): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $level          = (int) $level;
        $meetsLevel     = $user->role_level <= $level;
        $meetsRoleCode  = $allowedRoleCodes !== [] && in_array($user->role_code, $allowedRoleCodes, true);

        if (! $meetsLevel && ! $meetsRoleCode) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Insufficient role level.'], 403);
            }
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
