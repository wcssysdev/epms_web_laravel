<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Global\Role;

/**
 * Block non-admin users when the company system is locked.
 * Company Admin (level 30) and above can still access.
 */
class CheckSystemLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && session('is_locked', false)) {
            // Allow admins through
            if ($user->role_level <= Role::COMPANY_ADMIN) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'System is locked. Please contact your manager.',
                ], 423);
            }

            return redirect()->route('dashboard')
                ->with('error', 'System is locked. Please contact your manager for more information.');
        }

        return $next($request);
    }
}
