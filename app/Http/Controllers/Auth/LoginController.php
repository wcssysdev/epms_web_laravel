<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Transaction\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:6',
        ], [
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ]);

        $user = User::with(['access.role', 'access.company.config', 'access.country'])
            ->where('username', $request->username)
            ->first();

        // Check user exists & is active
        if (! $user || ! $user->is_active) {
            return back()->withErrors(['username' => 'Invalid credentials or account is inactive.'])->withInput();
        }

        // Check password
        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['username' => 'Invalid credentials or account is inactive.'])->withInput();
        }

        // Check user has access record
        if (! $user->access) {
            return back()->withErrors(['username' => 'Account has no role assigned. Contact administrator.'])->withInput();
        }

        // Login
        Auth::login($user, $request->boolean('remember'));

        // Update last login
        $user->updateLastLogin();

        // Store key session data
        session([
            'user_id'      => $user->id,
            'user_name'    => $user->user_name,
            'username'     => $user->username,
            'role_level'   => $user->role_level,
            'role_name'    => $user->role_name,
            'company_id'   => $user->company_id,
            'company_code' => $user->company_code,
            'company_name' => $user->company_name,
            'country_id'   => $user->country_id,
            'estate_name'  => $user->estate_name,
            'estate_code'  => $user->estate_code,
            'sap_client'   => $user->sap_client,
            'is_palm'      => $user->companyConfig?->system_is_palm ?? false,
            'is_coconut'   => $user->companyConfig?->system_is_coconut ?? false,
            'is_durian'    => $user->companyConfig?->system_is_durian ?? false,
            'is_rubber'    => $user->companyConfig?->system_is_rubber ?? false,
            'is_locked'    => $user->companyConfig?->is_lock_system ?? false,
        ]);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
