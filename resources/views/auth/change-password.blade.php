@extends('layouts.app')

@section('title', 'Change Password')

@section('breadcrumb')
    <li>Change Password</li>
@endsection

@section('page-title', 'Change Password')
@section('page-subtitle', 'Update your account password')

@section('content')
<div class="max-w-md">
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">

            <form method="POST" action="{{ route('change-password.post') }}">
                @csrf

                {{-- Current Password --}}
                <div class="form-control mb-4" x-data="{ show: false }">
                    <label class="label pb-1">
                        <span class="label-text font-medium">Current Password</span>
                    </label>
                    <label class="input input-bordered flex items-center gap-2 {{ $errors->has('current_password') ? 'input-error' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                        </svg>
                        <input :type="show ? 'text' : 'password'"
                               name="current_password"
                               placeholder="Current password"
                               class="grow bg-transparent outline-none text-sm"/>
                        <button type="button" @click="show = !show" class="text-base-content/40 hover:text-base-content">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </label>
                    @error('current_password')
                    <label class="label pt-1"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                {{-- New Password --}}
                <div class="form-control mb-4" x-data="{ show: false }">
                    <label class="label pb-1">
                        <span class="label-text font-medium">New Password</span>
                    </label>
                    <label class="input input-bordered flex items-center gap-2 {{ $errors->has('new_password') ? 'input-error' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                        </svg>
                        <input :type="show ? 'text' : 'password'"
                               name="new_password"
                               placeholder="New password"
                               class="grow bg-transparent outline-none text-sm"/>
                        <button type="button" @click="show = !show" class="text-base-content/40 hover:text-base-content">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </label>
                    @error('new_password')
                    <label class="label pt-1"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                    <label class="label pt-1">
                        <span class="label-text-alt text-base-content/40">Min 8 chars, must contain uppercase, lowercase, number & special char</span>
                    </label>
                </div>

                {{-- Confirm Password --}}
                <div class="form-control mb-6" x-data="{ show: false }">
                    <label class="label pb-1">
                        <span class="label-text font-medium">Confirm New Password</span>
                    </label>
                    <label class="input input-bordered flex items-center gap-2 {{ $errors->has('new_password_confirmation') ? 'input-error' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                        </svg>
                        <input :type="show ? 'text' : 'password'"
                               name="new_password_confirmation"
                               placeholder="Repeat new password"
                               class="grow bg-transparent outline-none text-sm"/>
                        <button type="button" @click="show = !show" class="text-base-content/40 hover:text-base-content">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </label>
                    @error('new_password_confirmation')
                    <label class="label pt-1"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn btn-primary flex-1">Update Password</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost flex-1">Cancel</a>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
