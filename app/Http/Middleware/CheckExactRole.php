<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exact-role gate. Grants access ONLY when the authenticated user's
 * role_code is one of the explicitly listed codes — regardless of level.
 *
 * This mirrors the CI3 sidebar rules, where visibility is decided by the
 * exact role (e.g. Planning = Assistant Manager only), not by a privilege
 * hierarchy. Use it when a lower-level (more privileged) role must NOT
 * automatically inherit a higher-level role's screens.
 *
 * Usage in routes:
 *   ->middleware('roles:asst_manager')
 *   ->middleware('roles:estate_manager,asst_manager')
 *   ->middleware('roles:super_admin,country_admin,company_admin,admin,it_staff')
 */
class CheckExactRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoleCodes): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! in_array($user->role_code, $allowedRoleCodes, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Your role cannot access this page.'], 403);
            }
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
