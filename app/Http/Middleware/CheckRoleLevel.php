<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check that the authenticated user has a role level
 * less than or equal to the required level.
 *
 * Usage in routes:
 *   ->middleware('role:30')   // Company Admin and above
 *   ->middleware('role:20')   // Country Admin and above
 *   ->middleware('role:10')   // Super Admin only
 */
class CheckRoleLevel
{
    public function handle(Request $request, Closure $next, int $level = 70): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->role_level > $level) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Insufficient role level.'], 403);
            }
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
