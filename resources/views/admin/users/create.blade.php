@extends('layouts.app')

@section('title', 'Add User')

@section('breadcrumb')
    <li><a href="{{ route('admin.users.index') }}" class="font-medium text-gray-500 hover:text-primary">Account Settings /</a></li>
    <li><span class="font-medium text-primary">Add User</span></li>
@endsection

@section('page-title', 'Add User')
@section('page-subtitle', 'Create a new user account')

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

        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="font-semibold text-sm" style="color: var(--epms-text);">User Information</h2>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="p-5" x-data="createUserForm()">
            @csrf

            {{-- Row 1: Display Name + Username --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="user_name"  label="Display Name" required placeholder="Full name" />
                <x-form.input name="username"   label="Username"     required placeholder="Login username" />
            </div>

            {{-- Row 2: Email --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="email" label="Email" type="email" placeholder="Optional" />

                {{-- Role --}}
                <x-form.select name="role_id" label="Role" required>
                    <option value="">— Select Role —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                            {{ $role->role_name }}
                        </option>
                    @endforeach
                </x-form.select>
            </div>

            {{-- Row 3: Employee Code + Internal Code --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="user_employee_code"
                              label="Employee Code"
                              placeholder="e.g. EMP001" />
                <x-form.input name="user_internal_employee_code"
                              label="Internal Employee Code"
                              placeholder="Required for Asst. Manager role"
                              :hint="'Required if role is Assistant Manager'" />
            </div>

            {{-- Row 4: Password --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <div x-data="{ show: false }" class="form-control mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'"
                               name="password"
                               required
                               placeholder="Min 8 chars"
                               class="w-full rounded-lg border px-3.5 py-2.5 pr-10 text-sm outline-none focus:border-primary {{ $errors->has('password') ? 'border-red-400' : '' }}"
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
                    @error('password')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs" style="color: var(--epms-text-muted);">
                        Must contain uppercase, lowercase, number & special char
                    </p>
                </div>

                <div x-data="{ show: false }" class="form-control mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'"
                               name="password_confirmation"
                               required
                               placeholder="Repeat password"
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
                </div>
            </div>

            {{-- Company (only for super/country admin) --}}
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCountryAdmin())
            <x-form.select name="company_id" label="Company" required>
                <option value="">— Select Company —</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                        {{ $company->company_code }} — {{ $company->company_name }}
                    </option>
                @endforeach
            </x-form.select>
            @else
            <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
            @endif

            {{-- Status --}}
            <div class="mb-5">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', '1') == '1' ? 'checked' : '' }}
                           class="w-4 h-4 rounded border accent-primary"
                           style="border-color: var(--epms-border);">
                    <span class="text-sm font-medium" style="color: var(--epms-text);">Active Account</span>
                </label>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3 pt-4 border-t" style="border-color: var(--epms-border);">
                <button type="submit"
                        class="flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
                        :disabled="loading" x-data="{ loading: false }" @click="loading = true">
                    <span x-show="!loading">Save User</span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <span class="loading loading-spinner loading-xs"></span> Saving...
                    </span>
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

@push('scripts')
<script>
function createUserForm() {
    return {
        loading: false
    }
}
</script>
@endpush
