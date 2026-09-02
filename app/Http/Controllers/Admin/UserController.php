<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Models\Transaction\User;
use App\Models\Transaction\UserAccess;
use App\Models\Global\Role;
use App\Models\Global\Company;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UserController extends BaseController
{
    // ── Index ─────────────────────────────────────────────────────────────────
    public function index(): View
    {
        return view('admin.users.index');
    }

    // ── DataTables AJAX ───────────────────────────────────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        $actor = $this->currentUser;

        $query = User::with(['access.role', 'access.company'])
            ->visibleTo($actor)
            ->select('tc_user.*');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('role_name', fn($u) => $u->access?->role?->role_name ?? '-')
            ->addColumn('role_level', fn($u) => $u->access?->role?->level ?? 99)
            ->addColumn('company_name', fn($u) => $u->access?->company?->company_name ?? 'Global')
            ->addColumn('status_badge', function ($u) {
                if ($u->is_active) {
                    return '<span class="badge badge-sm bg-green-100 text-green-700 border-green-200">Active</span>';
                }
                return '<span class="badge badge-sm bg-red-100 text-red-700 border-red-200">Inactive</span>';
            })
            ->addColumn('last_login', fn($u) => $u->last_login_at?->format('d/m/Y H:i') ?? '-')
            ->addColumn('actions', function ($u) use ($actor) {
                // Protect super admin — cannot be edited by non-super-admin
                $isSelf   = $u->id === $actor->id;
                $isProtected = $u->role_level <= 10 && !$actor->isSuperAdmin();

                $html = '<div class="flex items-center gap-1">';

                if (!$isProtected) {
                    $html .= '<a href="' . route('admin.users.edit', $u->id) . '"
                               class="btn-action btn-edit" title="Edit">
                               <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                               </svg>
                             </a>';

                    if (!$isSelf) {
                        $html .= '<button onclick="resetPassword(' . $u->id . ', \'' . addslashes($u->user_name) . '\')"
                                          class="btn-action btn-warning" title="Reset Password">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                          </svg>
                                        </button>';

                        $toggleLabel = $u->is_active ? 'Deactivate' : 'Activate';
                        $toggleClass = $u->is_active ? 'btn-danger' : 'btn-success';
                        $html .= '<button onclick="toggleActive(' . $u->id . ', \'' . addslashes($u->user_name) . '\', ' . ($u->is_active ? 'true' : 'false') . ')"
                                          class="btn-action ' . $toggleClass . '" title="' . $toggleLabel . '">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                          </svg>
                                        </button>';

                        $html .= '<button onclick="resetSession(' . $u->id . ', \'' . addslashes($u->user_name) . '\')"
                                          class="btn-action btn-info" title="Reset Session">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                          </svg>
                                        </button>';
                    }
                }

                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    // ── Create ────────────────────────────────────────────────────────────────
    public function create(): View
    {
        $roles     = $this->getAvailableRoles();
        $companies = $this->getAvailableCompanies();
        return view('admin.users.create', compact('roles', 'companies'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────
    public function store(StoreUserRequest $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $data = $request->validated();

        // Create user
        $user = User::create([
            'username'                    => $data['username'],
            'email'                       => $data['email'] ?? null,
            'password'                    => Hash::make($data['password']),
            'user_name'                   => $data['user_name'],
            'user_employee_code'          => $data['user_employee_code'] ?? null,
            'user_internal_employee_code' => $data['user_internal_employee_code'] ?? null,
            'is_active'                   => $data['is_active'] ?? true,
        ]);

        // Create access record
        UserAccess::create([
            'user_id'    => $user->id,
            'role_id'    => $data['role_id'],
            'company_id' => $request->input('company_id') ?? $this->companyId(),
            'country_id' => null,
            'is_active'  => true,
            'created_by' => $this->userName(),
            'created_at' => now(),
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ]);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_CREATE,
            "Created user: {$user->username} ({$user->user_name})"
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User \"{$user->user_name}\" created successfully.");
    }

    // ── Edit ──────────────────────────────────────────────────────────────────
    public function edit(int $id): View|RedirectResponse
    {
        $user = User::with(['access.role', 'access.company'])->find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')
                ->with('error', 'User not found.');
        }

        // Protect super admin
        if ($user->role_level <= 10 && !$this->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot edit Super Admin account.');
        }

        $roles     = $this->getAvailableRoles();
        $companies = $this->getAvailableCompanies();

        return view('admin.users.edit', compact('user', 'roles', 'companies'));
    }

    // ── Update ────────────────────────────────────────────────────────────────
    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $user = User::with('access')->find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User not found.');
        }

        $data = $request->validated();

        $user->update([
            'username'                    => $data['username'],
            'email'                       => $data['email'] ?? null,
            'user_name'                   => $data['user_name'],
            'user_employee_code'          => $data['user_employee_code'] ?? null,
            'user_internal_employee_code' => $data['user_internal_employee_code'] ?? null,
            'is_active'                   => $data['is_active'] ?? true,
        ]);

        // Update access
        if ($user->access) {
            $user->access->update([
                'role_id'    => $data['role_id'],
                'company_id' => $request->input('company_id') ?? $user->access->company_id,
                'updated_by' => $this->userName(),
                'updated_at' => now(),
            ]);
        }

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_UPDATE,
            "Updated user: {$user->username} ({$user->user_name})"
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User \"{$user->user_name}\" updated successfully.");
    }

    // ── Reset Password ────────────────────────────────────────────────────────
    public function resetPassword(ResetPasswordRequest $request, int $id): JsonResponse
    {
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $user = User::find($id);
        if (!$user) return $this->jsonError('User not found.', null, 404);

        $user->update(['password' => Hash::make($request->validated('new_password'))]);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_UPDATE,
            "Reset password for user: {$user->username}"
        );

        return $this->jsonSuccess("Password for \"{$user->user_name}\" has been reset.");
    }

    // ── Toggle Active ─────────────────────────────────────────────────────────
    public function toggleActive(int $id): JsonResponse
    {
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $user = User::find($id);
        if (!$user) return $this->jsonError('User not found.', null, 404);

        // Cannot deactivate self
        if ($user->id === $this->userId()) {
            return $this->jsonError('Cannot deactivate your own account.');
        }

        $user->update(['is_active' => !$user->is_active]);

        $action = $user->is_active ? 'activated' : 'deactivated';

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_UPDATE,
            "User {$action}: {$user->username}"
        );

        return $this->jsonSuccess("User \"{$user->user_name}\" has been {$action}.", [
            'is_active' => $user->is_active,
        ]);
    }

    // ── Reset Session (clear token) ───────────────────────────────────────────
    public function resetSession(int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->jsonError('User not found.', null, 404);

        $user->clearToken();

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_UPDATE,
            "Reset login session for user: {$user->username}"
        );

        return $this->jsonSuccess("Login session for \"{$user->user_name}\" has been reset.");
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Get roles available for the current company config.
     * Filters out palm/coconut/durian roles if not enabled.
     */
    private function getAvailableRoles(): \Illuminate\Database\Eloquent\Collection
    {
        $config = $this->companyConfig;

        $isPalm    = (bool) ($config?->system_is_palm    ?? false);
        $isCoconut = (bool) ($config?->system_is_coconut ?? false);
        $isDurian  = (bool) ($config?->system_is_durian  ?? false);
        $isRubber  = (bool) ($config?->system_is_rubber  ?? false);

        return Role::active()
            ->where('level', '>=', 30)
            ->where(function ($q) use ($isPalm, $isCoconut, $isDurian, $isRubber) {
                // Always include roles with no system type requirement
                $q->whereNull('required_system_type');

                // Include type-specific roles only if that system is enabled
                if ($isPalm)    $q->orWhere('required_system_type', 'palm');
                if ($isCoconut) $q->orWhere('required_system_type', 'coconut');
                if ($isDurian)  $q->orWhere('required_system_type', 'durian');
                if ($isRubber)  $q->orWhere('required_system_type', 'rubber');
            })
            ->orderBy('level')
            ->orderBy('role_name')
            ->get();
    }

    /**
     * Get companies available for assignment.
     * Super/Country Admin can assign to any company in scope.
     * Company Admin can only assign to own company.
     */
    private function getAvailableCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->isSuperAdmin()) {
            return Company::active()->orderBy('company_name')->get();
        }

        if ($this->isCountryAdmin()) {
            return Company::active()
                ->where('country_id', $this->countryId())
                ->orderBy('company_name')
                ->get();
        }

        // Company Admin — only own company
        return Company::where('id', $this->companyId())->get();
    }
}
