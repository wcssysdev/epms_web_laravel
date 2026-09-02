@extends('layouts.app')

@section('title', 'Estate Settings')

@section('breadcrumb')
    <li><span class="font-medium text-primary">Estate Settings</span></li>
@endsection

@section('page-title', 'Estate Settings')
@section('page-subtitle', 'Configure estate, integration type, and system settings')

@section('content')
<div x-data="estateSettings()" class="space-y-5">

    {{-- ── SYSTEM LOCK BANNER ─────────────────────────────────────────────── --}}
    @if(session('is_locked'))
    <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
        <span class="text-xl">🔒</span>
        <div class="flex-1">
            <p class="font-semibold text-red-700 text-sm">System is currently LOCKED</p>
            <p class="text-xs text-red-500 mt-0.5">Users cannot perform transactions until unlocked.</p>
        </div>
        @if(auth()->user()->role_level <= 30)
        <button @click="confirmToggleLock(false)"
                class="rounded-lg bg-red-600 px-4 py-2 text-xs font-medium text-white hover:bg-red-700 transition">
            Unlock System
        </button>
        @endif
    </div>
    @endif

    <form method="POST" action="{{ route('admin.config.update') }}">
        @csrf
        @method('PUT')

        {{-- ── SECTION: Identity ──────────────────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center gap-2"
                 style="border-color: var(--epms-border);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9"/>
                </svg>
                <h2 class="font-semibold text-sm" style="color: var(--epms-text);">Company & Estate Identity</h2>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                    {{-- Company Info (read only — managed from company master) --}}
                    <div class="mb-4">
                        <label class="block text-xs font-medium mb-1 uppercase tracking-wide"
                               style="color: var(--epms-text-muted);">Company Code</label>
                        <input type="text" value="{{ auth()->user()->company_code }}" disabled
                               class="w-full rounded-lg border px-3.5 py-2.5 text-sm opacity-60 cursor-not-allowed"
                               style="background: var(--epms-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <input type="hidden" name="company_code" value="{{ auth()->user()->company_code }}">
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-medium mb-1 uppercase tracking-wide"
                               style="color: var(--epms-text-muted);">Company Name <span class="text-red-500">*</span></label>
                        <input type="text" name="company_name"
                               value="{{ old('company_name', auth()->user()->company_name) }}"
                               placeholder="Company name"
                               class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary {{ $errors->has('company_name') ? 'border-red-400' : '' }}"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        @error('company_name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <x-form.input name="profile_code" label="Profile Code" required
                                  :value="$config?->profile_code" placeholder="e.g. P001"/>
                    <x-form.input name="profile_name" label="Profile Name" required
                                  :value="$config?->profile_name" placeholder="Profile name"/>
                    <x-form.input name="estate_code" label="Estate Code" required
                                  :value="$config?->estate_code" placeholder="e.g. TST"/>
                    <x-form.input name="estate_name" label="Estate Name" required
                                  :value="$config?->estate_name" placeholder="Estate name"/>
                    <x-form.input name="plant_code" label="Plant Code" required
                                  :value="$config?->plant_code" placeholder="SAP Plant Code"/>
                    <x-form.input name="sap_client" label="SAP Client"
                                  :value="$config?->sap_client ?? '000'"
                                  placeholder="e.g. 100"
                                  hint="Used for topbar badge display"/>
                </div>
            </div>
        </div>

        {{-- ── SECTION: System Type ────────────────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center gap-2"
                 style="border-color: var(--epms-border);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                </svg>
                <h2 class="font-semibold text-sm" style="color: var(--epms-text);">System Type</h2>
                <p class="text-xs ml-auto" style="color: var(--epms-text-muted);">Enabled types determine available roles and modules</p>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach([
                        ['key' => 'system_is_palm',    'label' => 'Palm',    'icon' => '🌴', 'color' => 'green'],
                        ['key' => 'system_is_coconut', 'label' => 'Coconut', 'icon' => '🥥', 'color' => 'yellow'],
                        ['key' => 'system_is_rubber',  'label' => 'Rubber',  'icon' => '🌿', 'color' => 'teal'],
                        ['key' => 'system_is_durian',  'label' => 'Durian',  'icon' => '🟡', 'color' => 'orange'],
                    ] as $type)
                    <label class="flex flex-col items-center gap-2 rounded-xl border p-4 cursor-pointer transition hover:border-primary group"
                           style="border-color: var(--epms-border);">
                        <input type="hidden" name="{{ $type['key'] }}" value="0">
                        <input type="checkbox"
                               name="{{ $type['key'] }}" value="1"
                               {{ old($type['key'], $config?->{$type['key']}) ? 'checked' : '' }}
                               class="sr-only peer">
                        <span class="text-3xl transition group-hover:scale-110">{{ $type['icon'] }}</span>
                        <span class="text-sm font-medium" style="color: var(--epms-text);">{{ $type['label'] }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full peer-checked:bg-green-100 peer-checked:text-green-700 bg-gray-100 text-gray-400 transition">
                            <span class="peer-checked:hidden">Disabled</span>
                            <span class="hidden peer-checked:inline">Enabled</span>
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── SECTION: Integration ────────────────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center justify-between"
                 style="border-color: var(--epms-border);">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <h2 class="font-semibold text-sm" style="color: var(--epms-text);">SAP / Integration</h2>
                </div>
                {{-- Test Connection Button --}}
                <button type="button" @click="testSapConnection()"
                        class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:border-primary hover:text-primary"
                        style="border-color: var(--epms-border); color: var(--epms-text-muted);">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span x-text="testingConn ? 'Testing...' : 'Test Connection'"></span>
                </button>
            </div>
            <div class="p-5">
                {{-- SAP test result --}}
                <div x-show="connResult" x-transition class="mb-4 rounded-lg border px-4 py-3 text-sm"
                     :class="connSuccess ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'">
                    <span x-text="connResult"></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                    {{-- Integration Type --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                            Integration Type <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-6 mt-2">
                            @foreach([1 => 'SAP', 2 => 'Pinfosys'] as $val => $label)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="integration_type" value="{{ $val }}"
                                       {{ old('integration_type', $config?->integration_type ?? 1) == $val ? 'checked' : '' }}
                                       class="accent-primary">
                                <span class="text-sm" style="color: var(--epms-text);">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Internet Connection --}}
                    <div class="mb-4 flex items-center">
                        <label class="flex items-center gap-3 cursor-pointer mt-5">
                            <input type="hidden" name="have_internet_connection" value="0">
                            <input type="checkbox" name="have_internet_connection" value="1"
                                   {{ old('have_internet_connection', $config?->have_internet_connection ?? true) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border accent-primary">
                            <span class="text-sm font-medium" style="color: var(--epms-text);">Have Internet Connection</span>
                        </label>
                    </div>

                    <x-form.input name="sap_api_url" label="SAP API URL" type="url"
                                  :value="$config?->sap_api_url"
                                  placeholder="https://sap-server/api"
                                  hint="Changes affect all master data sync operations"/>
                    <x-form.input name="sap_user_id" label="SAP User ID"
                                  :value="$config?->sap_user_id" placeholder="SAP username"/>
                    <div class="mb-4" x-data="{ show: false }">
                        <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">SAP Password</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="sap_password"
                                   placeholder="Leave blank to keep current"
                                   class="w-full rounded-lg border px-3.5 py-2.5 pr-10 text-sm outline-none focus:border-primary"
                                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            <button type="button" @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2"
                                    style="color: var(--epms-text-muted);">
                                <svg x-show="!show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        <p class="mt-1 text-xs" style="color: var(--epms-text-muted);">Leave blank to keep current password</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── SECTION: Attendance & Overtime ─────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center gap-2"
                 style="border-color: var(--epms-border);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h2 class="font-semibold text-sm" style="color: var(--epms-text);">Attendance & Overtime</h2>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                    {{-- Attendance Default (QR) --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                            Attendance Default QR Code Status <span class="text-red-500">*</span>
                        </label>
                        <select name="attendance_default_value"
                                class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary {{ $errors->has('attendance_default_value') ? 'border-red-400' : '' }}"
                                style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            <option value="">— Select —</option>
                            @foreach($attendanceCodes as $att)
                            <option value="{{ $att->attendance_code }}"
                                    {{ old('attendance_default_value', $config?->attendance_default_value) == $att->attendance_code ? 'selected' : '' }}>
                                {{ $att->attendance_code }} - {{ $att->attendance_desc }}
                            </option>
                            @endforeach
                        </select>
                        @error('attendance_default_value')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Attendance Normal Default --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                            Attendance Default Status <span class="text-red-500">*</span>
                        </label>
                        <select name="attendance_normal_default_value"
                                class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary {{ $errors->has('attendance_normal_default_value') ? 'border-red-400' : '' }}"
                                style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            <option value="">— Select —</option>
                            @foreach($attendanceCodes as $att)
                            <option value="{{ $att->attendance_code }}"
                                    {{ old('attendance_normal_default_value', $config?->attendance_normal_default_value) == $att->attendance_code ? 'selected' : '' }}>
                                {{ $att->attendance_code }} - {{ $att->attendance_desc }}
                            </option>
                            @endforeach
                        </select>
                        @error('attendance_normal_default_value')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Allowed Attendance Codes --}}
                    <div class="mb-4 md:col-span-2">
                        <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                            Allowed Attendance Codes for Work Assignment
                        </label>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($attendanceCodes as $att)
                            <label class="flex items-center gap-1.5 cursor-pointer rounded-lg border px-3 py-1.5 text-xs transition hover:border-primary"
                                   style="border-color: var(--epms-border);">
                                <input type="checkbox"
                                       name="allowed_attendance_codes[]"
                                       value="{{ $att->attendance_code }}"
                                       {{ in_array($att->attendance_code, old('allowed_attendance_codes', $config?->allowed_attendance_codes ?? [])) ? 'checked' : '' }}
                                       class="w-3 h-3 accent-primary">
                                <span style="color: var(--epms-text);">{{ $att->attendance_code }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <x-form.input name="daily_overtime_max_limit" label="Overtime Maximum Limit (hours)"
                                  type="number"
                                  :value="$config?->daily_overtime_max_limit ?? 3"
                                  hint="Default: 3 hours"/>
                    <x-form.input name="max_oph_restan" label="Maximum OPH Restan"
                                  type="number"
                                  :value="$config?->max_oph_restan ?? 0"/>
                </div>
            </div>
        </div>

        {{-- ── SECTION: Distribution Values ───────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center gap-2"
                 style="border-color: var(--epms-border);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <h2 class="font-semibold text-sm" style="color: var(--epms-text);">Distribution Values</h2>
                <span class="text-xs ml-auto" style="color: var(--epms-text-muted);">Cutter + Carrier must equal 100%</span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                    <x-form.input name="cutter_distribution_value"   label="Cutter Distribution Value (%)"    type="number" :value="$config?->cutter_distribution_value ?? 50"/>
                    <x-form.input name="carrier_distribution_value"  label="Carrier Distribution Value (%)"   type="number" :value="$config?->carrier_distribution_value ?? 50"/>
                    <x-form.input name="cutter_lf_distribution_value"  label="Cutter LF Distribution Value (%)" type="number" :value="$config?->cutter_lf_distribution_value"/>
                    <x-form.input name="carrier_lf_distribution_value" label="Carrier LF Distribution Value (%)" type="number" :value="$config?->carrier_lf_distribution_value"/>
                </div>
            </div>
        </div>

        {{-- ── SECTION: Operational Settings ─────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center gap-2"
                 style="border-color: var(--epms-border);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <h2 class="font-semibold text-sm" style="color: var(--epms-text);">Operational Settings</h2>
            </div>
            <div class="p-5">
                @php
                    $additionalSettings = $config?->additional_settings ?? [];
                    $checkboxGroups = [
                        'Take Picture mandatory in' => [
                            'take_picture_oph' => 'OPH', 'take_picture_cp1' => 'CP1',
                            'take_picture_cp2' => 'CP2', 'take_picture_fdn' => 'FDN',
                            'take_picture_hc'  => 'HC',  'take_picture_cp_coconut' => 'CP Coconut',
                            'take_picture_fdn_coconut' => 'FDN Coconut',
                        ],
                        'Take Location mandatory in' => [
                            'take_location_oph' => 'OPH', 'take_location_cp1' => 'CP1',
                            'take_location_cp2' => 'CP2', 'take_location_fdn' => 'FDN',
                        ],
                        'OPH Scan Options' => [
                            'oph_scan_task' => 'Scan Task', 'oph_scan_card' => 'Scan Card',
                        ],
                    ];
                @endphp

                <div class="space-y-5">
                    @foreach($checkboxGroups as $groupLabel => $items)
                    <div>
                        <p class="text-sm font-medium mb-2" style="color: var(--epms-text);">{{ $groupLabel }}</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach($items as $key => $label)
                            <label class="flex items-center gap-2 cursor-pointer rounded-lg border px-3 py-2 text-sm transition hover:border-primary"
                                   style="border-color: var(--epms-border);">
                                <input type="hidden" name="{{ $key }}" value="0">
                                <input type="checkbox" name="{{ $key }}" value="1"
                                       {{ ($additionalSettings[$key] ?? 'N') === 'Y' ? 'checked' : '' }}
                                       class="w-4 h-4 accent-primary">
                                <span style="color: var(--epms-text);">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                    {{-- FDN without OPH + Fixed Platform --}}
                    <div class="flex flex-wrap gap-4 pt-2 border-t" style="border-color: var(--epms-border);">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="fdn_oph" value="0">
                            <input type="checkbox" name="fdn_oph" value="1"
                                   {{ old('fdn_oph', $config?->fdn_oph) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded accent-primary">
                            <span class="text-sm" style="color: var(--epms-text);">FDN without OPH</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_fixed_platform" value="0">
                            <input type="checkbox" name="is_fixed_platform" value="1"
                                   {{ old('is_fixed_platform', $config?->is_fixed_platform) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded accent-primary">
                            <span class="text-sm" style="color: var(--epms-text);">Fixed Platform</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── SECTION: System Lock ────────────────────────────────────────── --}}
        @if(auth()->user()->role_level <= 30)
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center gap-2"
                 style="border-color: var(--epms-border);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <h2 class="font-semibold text-sm" style="color: var(--epms-text);">System Lock</h2>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between rounded-xl border p-4"
                     style="border-color: var(--epms-border);">
                    <div>
                        <p class="font-medium text-sm" style="color: var(--epms-text);">
                            System Status:
                            <span x-text="isLocked ? '🔒 LOCKED' : '🔓 OPEN'"
                                  :class="isLocked ? 'text-red-500' : 'text-green-600'"
                                  class="font-bold ml-1"></span>
                        </p>
                        <p class="text-xs mt-0.5" style="color: var(--epms-text-muted);">
                            When locked, only admins can access the system.
                        </p>
                    </div>
                    <button type="button"
                            @click="confirmToggleLock(isLocked)"
                            :class="isLocked
                                ? 'bg-green-600 hover:bg-green-700'
                                : 'bg-red-600 hover:bg-red-700'"
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <span x-text="isLocked ? 'Unlock System' : 'Lock System'"></span>
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ── SAVE BUTTON ─────────────────────────────────────────────────── --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="flex items-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save Settings
            </button>
            <a href="{{ route('dashboard') }}"
               class="rounded-lg border px-6 py-2.5 text-sm font-medium transition hover:opacity-80"
               style="border-color: var(--epms-border); color: var(--epms-text);">
                Cancel
            </a>
        </div>

    </form>

    {{-- ── SYSTEM LOCK CONFIRM MODAL ───────────────────────────────────────── --}}
    <div x-show="showLockModal"
         class="fixed inset-0 z-50 flex items-center justify-center"
         x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showLockModal = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-lg font-bold mb-2" style="color: var(--epms-text);"
                x-text="lockAction ? 'Unlock System' : 'Lock System'"></h3>
            <p class="text-sm mb-4" style="color: var(--epms-text-muted);"
               x-text="lockAction
                   ? 'Unlock the system so users can perform transactions again?'
                   : 'Lock the system? Only admins will be able to access until unlocked.'">
            </p>
            {{-- Reason (for unlock) --}}
            <div x-show="lockAction" class="mb-4">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">Unlock Reason (optional)</label>
                <input type="text" x-model="lockReason" placeholder="e.g. Maintenance complete"
                       class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary"
                       style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
            </div>
            <p id="lockError" class="text-sm text-red-500 mb-3 hidden"></p>
            <div class="flex gap-3 justify-end">
                <button @click="showLockModal = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">
                    Cancel
                </button>
                <button @click="doToggleLock()"
                        :class="lockAction ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition">
                    <span x-show="!lockLoading" x-text="lockAction ? 'Yes, Unlock' : 'Yes, Lock'"></span>
                    <span x-show="lockLoading" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function estateSettings() {
    return {
        // SAP test
        testingConn: false,
        connResult:  '',
        connSuccess: false,

        // System lock
        isLocked:      {{ session('is_locked') ? 'true' : 'false' }},
        showLockModal: false,
        lockAction:    false,  // false = lock, true = unlock
        lockReason:    '',
        lockLoading:   false,

        async testSapConnection() {
            this.testingConn = true;
            this.connResult  = '';
            try {
                const res  = await fetch('{{ route("admin.config.test-sap") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const json = await res.json();
                this.connSuccess = json.success;
                this.connResult  = json.message;
            } catch (e) {
                this.connSuccess = false;
                this.connResult  = 'Error: ' . e.message;
            } finally {
                this.testingConn = false;
            }
        },

        confirmToggleLock(currentlyLocked) {
            this.lockAction    = currentlyLocked;  // true = currently locked → will unlock
            this.lockReason    = '';
            this.showLockModal = true;
            document.getElementById('lockError').classList.add('hidden');
        },

        async doToggleLock() {
            this.lockLoading = true;
            try {
                const res  = await fetch('{{ route("admin.config.toggle-lock") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ reason: this.lockReason })
                });
                const json = await res.json();
                if (json.success) {
                    this.isLocked      = json.data.is_locked;
                    this.showLockModal = false;
                    // Reload page to refresh lock banner
                    window.location.reload();
                } else {
                    document.getElementById('lockError').textContent = json.message;
                    document.getElementById('lockError').classList.remove('hidden');
                }
            } catch (e) {
                document.getElementById('lockError').textContent = 'An error occurred.';
                document.getElementById('lockError').classList.remove('hidden');
            } finally {
                this.lockLoading = false;
            }
        }
    }
}
</script>
@endpush
