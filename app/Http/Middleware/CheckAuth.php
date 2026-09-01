<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login')->with('error', 'Please login to continue.');
        }

        $user = Auth::user();

        // Check if user still active
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')->with('error', 'Your account has been deactivated.');
        }

        // Check if user has access record
        if (! $user->access) {
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')->with('error', 'Account has no role assigned. Contact administrator.');
        }

        // Refresh session lock status from DB on each request
        $isLocked = $user->companyConfig?->is_lock_system ?? false;
        session(['is_locked' => $isLocked]);

        return $next($request);
    }
}
