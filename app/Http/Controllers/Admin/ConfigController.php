<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Global\CompanyConfig;
use App\Models\Global\Company;
use App\Models\Master\Attendance;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ConfigController extends BaseController
{
    // ── Index / View ──────────────────────────────────────────────────────────
    public function index(): View|RedirectResponse
    {
        // Estate Settings is company-scoped. Cross-company actors
        // (super_admin / country_admin) have no single company context.
        if (! $this->companyId()) {
            return redirect()->route('dashboard')->with(
                'error',
                'Estate Settings must be accessed within a specific company context. '
                . 'Your account is not scoped to a single company.'
            );
        }

        $config = $this->getOrCreateConfig();

        // Attendance codes for dropdown
        $attendanceCodes = DB::table('m_attendance')
            ->orderBy('attendance_code')
            ->get(['attendance_code', 'attendance_desc']);

        return view('admin.config.index', compact('config', 'attendanceCodes'));
    }

    // ── Update ────────────────────────────────────────────────────────────────
    public function update(Request $request): RedirectResponse
    {
        // Estate Settings is company-scoped; block cross-company actors.
        if (! $this->companyId()) {
            return redirect()->route('dashboard')->with(
                'error',
                'Estate Settings must be accessed within a specific company context.'
            );
        }

        // Validate
        $request->validate([
            'company_code'                  => 'required|string|max:20',
            'company_name'                  => 'required|string|max:150',
            'profile_code'                  => 'required|string|max:50',
            'profile_name'                  => 'required|string|max:100',
            'estate_code'                   => 'required|string|max:20',
            'estate_name'                   => 'required|string|max:100',
            'plant_code'                    => 'required|string|max:20',
            'sap_client'                    => 'nullable|string|max:10',
            'integration_type'              => 'required|in:1,2',
            'sap_api_url'                   => 'nullable|url|max:500',
            'sap_user_id'                   => 'nullable|string|max:100',
            'sap_password'                  => 'nullable|string|max:255',
            'cutter_distribution_value'     => 'required|numeric|min:0|max:100',
            'carrier_distribution_value'    => 'required|numeric|min:0|max:100',
            'cutter_lf_distribution_value'  => 'nullable|numeric|min:0|max:100',
            'carrier_lf_distribution_value' => 'nullable|numeric|min:0|max:100',
            'attendance_default_value'      => 'required|string|max:20',
            'attendance_normal_default_value' => 'required|string|max:20',
            'allowed_attendance_codes'      => 'nullable|array',
            'daily_overtime_max_limit'      => 'required|integer|min:0|max:24',
            'max_oph_restan'                => 'required|integer|min:0',
            'fdn_oph'                       => 'nullable',
            'is_fixed_platform'             => 'nullable',
        ]);

        // Validate cutter + carrier = 100
        $cutter  = (float) $request->cutter_distribution_value;
        $carrier = (float) $request->carrier_distribution_value;
        if (($cutter + $carrier) != 100) {
            return back()
                ->withInput()
                ->with('error', 'Cutter + Carrier distribution value must equal 100%. Current: ' . ($cutter + $carrier) . '%');
        }

        $config = $this->getOrCreateConfig();

        // Collect additional_settings fields
        $additionalKeys = [
            'take_picture_oph', 'take_picture_cp1', 'take_picture_cp2', 'take_picture_fdn',
            'take_location_oph', 'take_location_cp1', 'take_location_cp2', 'take_location_fdn',
            'take_picture_hc', 'take_picture_cp_coconut', 'take_picture_fdn_coconut',
            'oph_scan_task', 'oph_scan_card',
        ];
        $additionalSettings = [];
        foreach ($additionalKeys as $key) {
            $additionalSettings[$key] = $request->has($key) ? 'Y' : 'N';
        }

        // Update company name on m_company if changed
        if ($this->companyId()) {
            Company::where('id', $this->companyId())
                ->update([
                    'company_name' => $request->company_name,
                    'updated_at'   => now(),
                ]);
        }

        // Save password only if provided (don't overwrite with empty)
        $sapPassword = $request->sap_password
            ? $request->sap_password
            : $config->sap_password;

        $config->update([
            'profile_code'                  => $request->profile_code,
            'profile_name'                  => $request->profile_name,
            'estate_code'                   => $request->estate_code,
            'estate_name'                   => $request->estate_name,
            'plant_code'                    => $request->plant_code,
            'sap_client'                    => $request->sap_client ?? '000',
            'system_is_palm'                => $request->boolean('system_is_palm'),
            'system_is_coconut'             => $request->boolean('system_is_coconut'),
            'system_is_rubber'              => $request->boolean('system_is_rubber'),
            'system_is_durian'              => $request->boolean('system_is_durian'),
            'integration_type'              => (int) $request->integration_type,
            'have_internet_connection'      => $request->boolean('have_internet_connection'),
            'sap_api_url'                   => $request->sap_api_url,
            'sap_user_id'                   => $request->sap_user_id,
            'sap_password'                  => $sapPassword,
            'cutter_distribution_value'     => $cutter,
            'carrier_distribution_value'    => $carrier,
            'cutter_lf_distribution_value'  => $request->cutter_lf_distribution_value,
            'carrier_lf_distribution_value' => $request->carrier_lf_distribution_value,
            'attendance_default_value'      => $request->attendance_default_value,
            'attendance_normal_default_value' => $request->attendance_normal_default_value,
            'allowed_attendance_codes'      => $request->allowed_attendance_codes ?? [],
            'daily_overtime_max_limit'      => (int) $request->daily_overtime_max_limit,
            'max_oph_restan'                => (int) $request->max_oph_restan,
            'fdn_oph'                       => $request->boolean('fdn_oph'),
            'is_fixed_platform'             => $request->boolean('is_fixed_platform'),
            'additional_settings'           => $additionalSettings,
            'updated_by'                    => $this->userName(),
            'updated_at'                    => now(),
        ]);

        // Refresh session with updated config
        session([
            'estate_name' => $config->fresh()->estate_name,
            'estate_code' => $config->fresh()->estate_code,
            'sap_client'  => $config->fresh()->sap_client,
            'is_palm'     => $config->fresh()->system_is_palm,
            'is_coconut'  => $config->fresh()->system_is_coconut,
            'is_durian'   => $config->fresh()->system_is_durian,
            'is_rubber'   => $config->fresh()->system_is_rubber,
        ]);

        AuditService::log(
            AuditService::TYPE_SYSTEM,
            AuditService::ACTION_UPDATE,
            'Estate settings updated by ' . $this->userName()
        );

        return back()->with('success', 'Estate settings saved successfully.');
    }

    // ── Test SAP Connection ───────────────────────────────────────────────────
    public function testSapConnection(): JsonResponse
    {
        if (! $this->companyId()) {
            return $this->jsonError('No company context. Estate Settings is company-scoped.');
        }

        $config = $this->getOrCreateConfig();
        $result = $config->testSapConnection();

        return response()->json($result);
    }

    // ── Toggle System Lock ────────────────────────────────────────────────────
    public function toggleSystemLock(Request $request): JsonResponse
    {
        // Only Company Admin (level 30) and above
        if ($this->roleLevel() > 30) {
            return $this->jsonError('Insufficient permission to lock/unlock system.');
        }

        if (! $this->companyId()) {
            return $this->jsonError('No company context. Estate Settings is company-scoped.');
        }

        $config = $this->getOrCreateConfig();
        $newState = !$config->is_lock_system;

        $config->update([
            'is_lock_system' => $newState,
            'updated_by'     => $this->userName(),
            'updated_at'     => now(),
        ]);

        // Log to system lock table
        DB::table('log_system_lock')->insert([
            'company_id'    => $this->companyId(),
            'is_locked'     => $newState,
            'locked_at'     => now(),
            'unlocked_at'   => $newState ? null : now(),
            'unlocked_by'   => $newState ? null : $this->userId(),
            'unlock_reason' => $request->input('reason'),
        ]);

        // Update session
        session(['is_locked' => $newState]);

        $action = $newState ? 'locked' : 'unlocked';
        AuditService::log(
            AuditService::TYPE_SYSTEM,
            $newState ? AuditService::ACTION_LOCK : AuditService::ACTION_UNLOCK,
            "System {$action} by {$this->userName()}" . ($request->reason ? ": {$request->reason}" : '')
        );

        return $this->jsonSuccess(
            'System has been ' . ($newState ? 'locked' : 'unlocked') . ' successfully.',
            ['is_locked' => $newState]
        );
    }

    // ── Private: get or create config for current company ────────────────────
    private function getOrCreateConfig(): CompanyConfig
    {
        $companyId = $this->companyId();

        // Safety net: never create a CompanyConfig without a company context.
        abort_if(! $companyId, 403, 'Estate Settings requires a specific company context.');

        $config = CompanyConfig::where('company_id', $companyId)->first();

        if (!$config) {
            $config = CompanyConfig::create([
                'company_id'                => $companyId,
                'integration_type'          => 1,
                'daily_overtime_max_limit'  => 3,
                'max_oph_restan'            => 0,
                'is_lock_system'            => false,
                'system_is_palm'            => false,
                'system_is_coconut'         => false,
                'system_is_rubber'          => false,
                'system_is_durian'          => false,
                'cutter_distribution_value' => 50,
                'carrier_distribution_value' => 50,
                'created_by'               => $this->userName(),
                'created_at'               => now(),
                'updated_by'               => $this->userName(),
                'updated_at'               => now(),
            ]);
        }

        return $config;
    }
}
