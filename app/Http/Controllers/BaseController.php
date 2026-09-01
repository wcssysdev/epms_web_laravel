<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Transaction\User;
use App\Models\Global\CompanyConfig;

abstract class BaseController extends Controller
{
    protected ?User $currentUser = null;
    protected ?CompanyConfig $companyConfig = null;

    public function __construct()
    {
        // Inject current user + company config for all child controllers
        if (Auth::check()) {
            $this->currentUser   = Auth::user();
            $this->companyConfig = $this->currentUser->companyConfig;
        }
    }

    // ── Current User Helpers ─────────────────────────────────────────
    protected function userId(): ?int
    {
        return $this->currentUser?->id;
    }

    protected function userName(): string
    {
        return $this->currentUser?->user_name ?? session('user_name', '');
    }

    protected function companyId(): ?int
    {
        return $this->currentUser?->company_id ?? session('company_id');
    }

    protected function companyCode(): string
    {
        return $this->currentUser?->company_code ?? session('company_code', '');
    }

    protected function countryId(): ?int
    {
        return $this->currentUser?->country_id ?? session('country_id');
    }

    protected function roleLevel(): int
    {
        return $this->currentUser?->role_level ?? 99;
    }

    protected function estateCode(): string
    {
        return $this->companyConfig?->estate_code ?? session('estate_code', '');
    }

    protected function plantCode(): string
    {
        return $this->companyConfig?->plant_code ?? '';
    }

    protected function isSuperAdmin(): bool
    {
        return $this->currentUser?->isSuperAdmin() ?? false;
    }

    protected function isCountryAdmin(): bool
    {
        return $this->currentUser?->isCountryAdmin() ?? false;
    }

    protected function isSystemLocked(): bool
    {
        return $this->companyConfig?->is_lock_system ?? session('is_locked', false);
    }

    protected function isSystemEnabled(string $type): bool
    {
        return $this->companyConfig?->isSystemEnabled($type) ?? false;
    }

    // ── Audit Fields Helper ──────────────────────────────────────────
    /** Returns array with created_by + created_at for insert */
    protected function auditCreate(): array
    {
        return [
            'created_by' => $this->userName(),
            'created_at' => now(),
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ];
    }

    /** Returns array with updated_by + updated_at for update */
    protected function auditUpdate(): array
    {
        return [
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ];
    }

    // ── Response Helpers ─────────────────────────────────────────────
    protected function jsonSuccess(string $message, mixed $data = null, int $status = 200): \Illuminate\Http\JsonResponse
    {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        return response()->json($response, $status);
    }

    protected function jsonError(string $message, mixed $errors = null, int $status = 422): \Illuminate\Http\JsonResponse
    {
        $response = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $status);
    }

    // ── System Lock Guard ────────────────────────────────────────────
    /** Call this at the start of any write operation */
    protected function guardSystemLock(): ?\Illuminate\Http\RedirectResponse
    {
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return redirect()->back()
                ->with('error', 'System is locked. Please contact your manager.');
        }
        return null;
    }
}
