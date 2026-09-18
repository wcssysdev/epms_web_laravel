@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $resourceName)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $resourceName }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($item ? 'Edit' : 'Add') . ' ' . $resourceName)

@section('page-actions')
    <a href="{{ route($routePrefix . '.index') }}"
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
            <h2 class="text-sm font-semibold uppercase tracking-wide" style="color: var(--epms-text);">FORM ADD GROUPING ASSISTANT MANAGER - DIVISION</h2>
        </div>

        <form method="POST"
              action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}"
              class="p-6 space-y-5">
            @csrf
            @if($item) @method('PUT') @endif

            {{-- 1. Choose Assistant Manager --}}
            <div class="form-control">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Choose Assistant Manager <span class="text-red-500">*</span>
                </label>
                <select name="assistant_manager_code"
                        required
                        class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary {{ $errors->has('assistant_manager_code') ? 'border-red-400' : '' }}"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: {{ $errors->has('assistant_manager_code') ? '' : 'var(--epms-border)' }};">
                    <option value="" disabled {{ old('assistant_manager_code', $item?->assistant_manager_code) ? '' : 'selected' }}>Choose Assistant Manager</option>
                    @foreach($assistantManagers as $mgr)
                        @php
                            $codeVal = $mgr->user_internal_employee_code ?: $mgr->id;
                            $isSelected = old('assistant_manager_code', $item?->assistant_manager_code) == $codeVal;
                        @endphp
                        <option value="{{ $codeVal }}" {{ $isSelected ? 'selected' : '' }}>
                            {{ $codeVal }} - {{ $mgr->user_name ?: $mgr->username }}
                        </option>
                    @endforeach
                </select>
                @error('assistant_manager_code')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- 2. Choose Division --}}
            <div class="form-control">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Choose Division <span class="text-red-500">*</span>
                </label>
                <select name="division_code"
                        required
                        class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary {{ $errors->has('division_code') ? 'border-red-400' : '' }}"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: {{ $errors->has('division_code') ? '' : 'var(--epms-border)' }};">
                    <option value="" disabled {{ old('division_code', $item?->division_code) ? '' : 'selected' }}>Choose Division</option>
                    @foreach($divisions as $div)
                        @php
                            $isSelected = old('division_code', $item?->division_code) == $div->division_code;
                        @endphp
                        <option value="{{ $div->division_code }}" {{ $isSelected ? 'selected' : '' }}>
                            {{ $div->division_code }} - {{ $div->division_name }}
                        </option>
                    @endforeach
                </select>
                @error('division_code')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3 pt-4 border-t" style="border-color: var(--epms-border);">
                <button type="submit"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    {{ $item ? 'Update' : 'Save' }}
                </button>
                <a href="{{ route($routePrefix.'.index') }}"
                   class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                   style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
