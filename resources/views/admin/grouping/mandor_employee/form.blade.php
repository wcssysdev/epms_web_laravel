@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $resourceName)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $resourceName }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($item ? 'Edit' : 'Add') . ' Grouping Gang')

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
            <h2 class="text-sm font-semibold uppercase tracking-wide" style="color: var(--epms-text);">FORM ADD GROUPING GANG</h2>
        </div>
        
        <form method="POST"
              action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}"
              class="p-6 space-y-5"
              x-data="groupingGangForm({
                  initialMandorCode: '{{ addslashes(old('grouping_mandor_employee_code', $item?->mandor_employee_code ?? '')) }}',
                  initialMandorName: '{{ addslashes(old('mandor_employee_name', $item?->mandor_employee_name ?? '')) }}',
                  initialEmpCode: '{{ addslashes(old('grouping_employee_code', $item?->employee_code ?? '')) }}',
                  initialEmpName: '{{ addslashes(old('employee_name', $item?->employee_name ?? '')) }}',
                  mandorLookupUrl: '{{ route('grouping.mandor_employee.mandor-lookup') }}',
                  employeeLookupUrl: '{{ route('grouping.mandor_employee.employee-lookup') }}'
              })">
            @csrf
            @if($item) @method('PUT') @endif

            {{-- 1. Choose Gang (Mandor) --}}
            <div class="relative">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Choose Gang <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="hidden" name="grouping_mandor_employee_code" :value="mandorCode">
                    <input type="text"
                           x-model="mandorQuery"
                           @input="handleMandorInput"
                           @focus="if (mandorSuggestions.length > 0) mandorOpen = true"
                           @click.away="mandorOpen = false"
                           @keydown.escape="mandorOpen = false"
                           placeholder="Type to search Gang / Mandore (Code or Name)..."
                           required
                           autocomplete="off"
                           class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition
                                  focus:border-primary focus:ring-1 focus:ring-primary
                                  {{ $errors->has('grouping_mandor_employee_code') ? 'border-red-400' : '' }}"
                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: {{ $errors->has('grouping_mandor_employee_code') ? '' : 'var(--epms-border)' }};">

                    <!-- Loading spinner -->
                    <div x-show="mandorLoading" class="absolute right-3 top-3 text-primary" style="display: none;">
                        <svg class="animate-spin h-4 w-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Suggestions Dropdown -->
                <div x-show="mandorOpen && (mandorSuggestions.length > 0 || mandorNoResults)"
                     x-cloak
                     class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border shadow-xl"
                     style="background: var(--epms-card-bg, #ffffff); border-color: var(--epms-border);">
                    
                    <template x-for="(item, idx) in mandorSuggestions" :key="item.employee_code">
                        <div @click="selectMandor(item)"
                             class="px-4 py-2.5 text-sm cursor-pointer border-b last:border-b-0 hover:bg-primary/10 transition flex items-center justify-between"
                             style="border-color: var(--epms-border);">
                            <div>
                                <div class="font-semibold" style="color: var(--epms-text);" x-text="item.employee_code + ' - ' + item.employee_name"></div>
                            </div>
                            <span class="text-[11px] px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300" x-text="item.employee_job_code"></span>
                        </div>
                    </template>

                    <div x-show="mandorNoResults" class="px-4 py-3 text-xs text-gray-500 italic text-center">
                        No gang / mandore found matching "<span x-text="mandorQuery"></span>"
                    </div>
                </div>
                @error('grouping_mandor_employee_code')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- 2. Choose Employee --}}
            <div class="relative">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Choose Employee <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="hidden" name="grouping_employee_code" :value="empCode">
                    <input type="text"
                           x-model="empQuery"
                           @input="handleEmpInput"
                           @focus="if (empSuggestions.length > 0) empOpen = true"
                           @click.away="empOpen = false"
                           @keydown.escape="empOpen = false"
                           placeholder="Type to search Employee (Code or Name)..."
                           required
                           autocomplete="off"
                           class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition
                                  focus:border-primary focus:ring-1 focus:ring-primary
                                  {{ $errors->has('grouping_employee_code') ? 'border-red-400' : '' }}"
                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: {{ $errors->has('grouping_employee_code') ? '' : 'var(--epms-border)' }};">

                    <!-- Loading spinner -->
                    <div x-show="empLoading" class="absolute right-3 top-3 text-primary" style="display: none;">
                        <svg class="animate-spin h-4 w-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Suggestions Dropdown -->
                <div x-show="empOpen && (empSuggestions.length > 0 || empNoResults)"
                     x-cloak
                     class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border shadow-xl"
                     style="background: var(--epms-card-bg, #ffffff); border-color: var(--epms-border);">
                    
                    <template x-for="(item, idx) in empSuggestions" :key="item.employee_code">
                        <div @click="selectEmp(item)"
                             class="px-4 py-2.5 text-sm cursor-pointer border-b last:border-b-0 hover:bg-primary/10 transition flex items-center justify-between"
                             style="border-color: var(--epms-border);">
                            <div>
                                <div class="font-semibold" style="color: var(--epms-text);" x-text="item.employee_code + ' - ' + item.employee_name"></div>
                            </div>
                            <span class="text-[11px] px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300" x-text="item.employee_job_code"></span>
                        </div>
                    </template>

                    <div x-show="empNoResults" class="px-4 py-3 text-xs text-gray-500 italic text-center">
                        No employee found matching "<span x-text="empQuery"></span>"
                    </div>
                </div>
                @error('grouping_employee_code')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- 3. Validity Date Range (From - To) --}}
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Validity Date <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3 items-center">
                    <div>
                        <input type="date"
                               name="grouping_assignment_from"
                               value="{{ old('grouping_assignment_from', $item?->start_validity ? substr($item->start_validity,0,10) : date('Y-m-d')) }}"
                               required
                               class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        @error('grouping_assignment_from')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">to</span>
                        <div class="w-full">
                            <input type="date"
                                   name="grouping_assignment_to"
                                   value="{{ old('grouping_assignment_to', $item?->end_validity ? substr($item->end_validity,0,10) : '9999-12-31') }}"
                                   required
                                   class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            @error('grouping_assignment_to')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
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

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('groupingGangForm', (config) => ({
        // Mandor / Gang
        mandorCode: config.initialMandorCode || '',
        mandorQuery: config.initialMandorCode ? (config.initialMandorCode + (config.initialMandorName ? ' - ' + config.initialMandorName : '')) : '',
        mandorLookupUrl: config.mandorLookupUrl,
        mandorSuggestions: [],
        mandorOpen: false,
        mandorLoading: false,
        mandorNoResults: false,
        mandorTimer: null,

        // Employee
        empCode: config.initialEmpCode || '',
        empQuery: config.initialEmpCode ? (config.initialEmpCode + (config.initialEmpName ? ' - ' + config.initialEmpName : '')) : '',
        employeeLookupUrl: config.employeeLookupUrl,
        empSuggestions: [],
        empOpen: false,
        empLoading: false,
        empNoResults: false,
        empTimer: null,

        handleMandorInput() {
            clearTimeout(this.mandorTimer);
            const q = this.mandorQuery.trim();
            if (q.length < 1) {
                this.mandorSuggestions = [];
                this.mandorOpen = false;
                this.mandorNoResults = false;
                this.mandorLoading = false;
                this.mandorCode = '';
                return;
            }
            this.mandorLoading = true;
            this.mandorTimer = setTimeout(() => {
                this.fetchMandor(q);
            }, 300);
        },

        async fetchMandor(query) {
            try {
                const res = await fetch(`${this.mandorLookupUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                const list = Array.isArray(json) ? json : (json.data && Array.isArray(json.data) ? json.data : []);
                this.mandorSuggestions = list;
                this.mandorNoResults = list.length === 0;
                this.mandorOpen = true;
            } catch (e) {
                this.mandorSuggestions = [];
                this.mandorNoResults = true;
            } finally {
                this.mandorLoading = false;
            }
        },

        selectMandor(item) {
            this.mandorCode = item.employee_code;
            this.mandorQuery = item.employee_code + ' - ' + item.employee_name;
            this.mandorOpen = false;
            this.mandorSuggestions = [];
            this.mandorNoResults = false;
        },

        handleEmpInput() {
            clearTimeout(this.empTimer);
            const q = this.empQuery.trim();
            if (q.length < 1) {
                this.empSuggestions = [];
                this.empOpen = false;
                this.empNoResults = false;
                this.empLoading = false;
                this.empCode = '';
                return;
            }
            this.empLoading = true;
            this.empTimer = setTimeout(() => {
                this.fetchEmp(q);
            }, 300);
        },

        async fetchEmp(query) {
            try {
                const res = await fetch(`${this.employeeLookupUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                const list = Array.isArray(json) ? json : (json.data && Array.isArray(json.data) ? json.data : []);
                this.empSuggestions = list;
                this.empNoResults = list.length === 0;
                this.empOpen = true;
            } catch (e) {
                this.empSuggestions = [];
                this.empNoResults = true;
            } finally {
                this.empLoading = false;
            }
        },

        selectEmp(item) {
            this.empCode = item.employee_code;
            this.empQuery = item.employee_code + ' - ' + item.employee_name;
            this.empOpen = false;
            this.empSuggestions = [];
            this.empNoResults = false;
        }
    }));
});
</script>
@endsection
