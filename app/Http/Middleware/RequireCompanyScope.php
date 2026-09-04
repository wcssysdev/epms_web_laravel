<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require a single-company context for company-scoped screens
 * (Master Data, Grouping, Estate Settings). Cross-company actors
 * (super_admin / country_admin) have no single company_id and would
 * otherwise crash on queries that expect one, so they are redirected
 * with a clear message instead.
 *
 * Company scope is resolved from the user's UserAccess.company_id.
 */
class RequireCompanyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $companyId = $user->company_id ?? session('company_id');

        if (! $companyId) {
            $message = 'This section is company-scoped. Your account is not tied to a '
                . 'single company, so please switch to a specific company context first.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return redirect()->route('dashboard')->with('error', $message);
        }

        return $next($request);
    }
}
