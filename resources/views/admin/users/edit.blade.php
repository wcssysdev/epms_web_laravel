@extends('layouts.app')

@section('title', 'Edit User')

@section('breadcrumb')
    <li><a href="{{ route('admin.users.index') }}" class="font-medium text-gray-500 hover:text-primary">Account Settings /</a></li>
    <li><span class="font-medium text-primary">Edit User</span></li>
@endsection

@section('page-title', 'Edit User')
@section('page-subtitle', 'Update user account information')

@section('page-actions')
    <a href="{{ route('admin.users.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')

<div class="max-w-2xl">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">

        {{-- Header --}}
        <div class="flex items-center gap-4 px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white font-bold text-sm">
                {{ strtoupper(substr($user->user_name, 0, 1)) }}
            </div>
            <div>
                <p class="font-semibold text-sm" style="color: var(--epms-text);">{{ $user->user_name }}</p>
                <p class="text-xs" style="color: var(--epms-text-muted);">
                    {{ $user->username }}
                    @if($user->last_login_at)
                        &middot; Last login: {{ $user->last_login_at->format('d/m/Y H:i') }}
                    @else
                        &middot; Never logged in
                    @endif
                </p>
            </div>
            <div class="ml-auto">
                <span class="rounded-full px-3 py-1 text-xs font-medium
                    {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user->id) }}" class="p-5" x-data="editUserForm()">
            @csrf
            @method('PUT')

            {{-- Row 1: Name + Username --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="user_name" label="Display Name" required
                              :value="$user->user_name" placeholder="Full name" />
                <x-form.input name="username" label="Username" required
                              :value="$user->username" placeholder="Login username" />
            </div>

            {{-- Row 2: Email + Role --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="email" label="Email" type="email"
                              :value="$user->email" placeholder="Optional" />

                <div class="form-control mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                        Role <span class="text-red-500 ml-0.5">*</span>
                    </label>
                    <select name="role_id" id="role_id" required @change="onRoleChange($event)"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary {{ $errors->has('role_id') ? 'border-red-400' : '' }}"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">— Select Role —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" data-code="{{ $role->role_code }}"
                                {{ (old('role_id', $user->access?->role_id) == $role->id) ? 'selected' : '' }}>
                                {{ $role->role_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Row 3: Employee Codes --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="user_employee_code"
                              label="Employee Code"
                              :value="$user->user_employee_code"
                              placeholder="e.g. EMP001" />
                <x-form.input name="user_internal_employee_code"
                              label="Internal Employee Code"
                              :value="$user->user_internal_employee_code"
                              placeholder="Required for Asst. Manager role"
                              :hint="'Required if role is Assistant Manager'" />
            </div>

            {{-- Company (super/country admin only) --}}
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCountryAdmin())
            <x-form.select name="company_id" label="Company" required>
                <option value="">— Select Company —</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}"
                        {{ (old('company_id', $user->access?->company_id) == $company->id) ? 'selected' : '' }}>
                        {{ $company->company_code }} — {{ $company->company_name }}
                    </option>
                @endforeach
            </x-form.select>
            @else
            <input type="hidden" name="company_id" value="{{ $user->access?->company_id ?? auth()->user()->company_id }}">
            @endif

            {{-- Multi-estate scope (Plantation Controller / Company Staff) --}}
            <div x-show="wantsEstates" x-cloak class="mb-5">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Assigned Estates
                    <span class="text-xs font-normal" style="color: var(--epms-text-muted);">(multi-estate scope)</span>
                </label>
                <select name="scope_estates[]" multiple size="6"
                        class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    @foreach($scopeEstates as $e)
                        <option value="{{ $e->id }}" data-company="{{ $e->company_id }}"
                            {{ collect(old('scope_estates', $selectedEstateIds))->contains($e->id) ? 'selected' : '' }}>
                            {{ $e->estate_code }} — {{ $e->estate_name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs" style="color: var(--epms-text-muted);">Hold Ctrl/Cmd to select multiple estates.</p>
            </div>

            {{-- Multi-country scope (Country Admin) --}}
            @if($scopeCountries->count())
            <div x-show="wantsCountries" x-cloak class="mb-5">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Assigned Countries
                    <span class="text-xs font-normal" style="color: var(--epms-text-muted);">(multi-country scope)</span>
                </label>
                <select name="scope_countries[]" multiple size="4"
                        class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    @foreach($scopeCountries as $c)
                        <option value="{{ $c->id }}"
                            {{ collect(old('scope_countries', $selectedCountryIds))->contains($c->id) ? 'selected' : '' }}>
                            {{ $c->code }} — {{ $c->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs" style="color: var(--epms-text-muted);">Hold Ctrl/Cmd to select multiple countries.</p>
            </div>
            @endif

            {{-- Status --}}
            <div class="mb-5">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $user->is_active ? '1' : '0') == '1' ? 'checked' : '' }}
                           class="w-4 h-4 rounded border accent-primary">
                    <span class="text-sm font-medium" style="color: var(--epms-text);">Active Account</span>
                </label>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3 pt-4 border-t" style="border-color: var(--epms-border);">
                <button type="submit"
                        class="flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                    Update User
                </button>
                <a href="{{ route('admin.users.index') }}"
                   class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                   style="border-color: var(--epms-border); color: var(--epms-text);">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
<style>[x-cloak] { display: none !important; }</style>
@endpush

@push('scripts')
<script>
function editUserForm() {
    return {
        roleCode: '',
        get wantsEstates()   { return this.roleCode === 'pc' || this.roleCode === 'cs'; },
        get wantsCountries() { return this.roleCode === 'country_admin'; },
        init() {
            const sel = document.getElementById('role_id');
            if (sel && sel.selectedOptions.length) {
                this.roleCode = sel.selectedOptions[0].dataset.code || '';
            }
        },
        onRoleChange(e) {
            const opt = e.target.selectedOptions[0];
            this.roleCode = opt ? (opt.dataset.code || '') : '';
        }
    }
}
</script>
@endpush
