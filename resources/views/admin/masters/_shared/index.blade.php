@extends('layouts.app')

@section('title', $resourceName . ' Master')

@section('breadcrumb')
    <li><span class="font-medium text-primary">{{ $resourceName }}</span></li>
@endsection

@section('page-title', $resourceName . ' Master')
@section('page-subtitle', 'Manage ' . strtolower($resourceName) . ' master data')

@section('page-actions')
<div class="flex flex-wrap items-center gap-2">
    @if($hasSap ?? false)
    {{-- 1. Get All Data From SAP --}}
    <button type="button" @click="$store.masterSap.getFromSap()" :disabled="$store.masterSap.busy"
            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80 disabled:opacity-50"
            style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': $store.masterSap.busy==='get' }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Get All Data From SAP
    </button>

    {{-- 2. Refresh Master Data From SAP --}}
    <button type="button" @click="$store.masterSap.refreshFromMaster()" :disabled="$store.masterSap.busy"
            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80 disabled:opacity-50"
            style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': $store.masterSap.busy==='refresh' }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Refresh Master Data From SAP
    </button>
    @endif

    {{-- 3. Upload Data --}}
    <a href="{{ route($routePrefix . '.upload') }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        Upload Data
    </a>

    {{-- 4. Download Template --}}
    <a href="{{ route($routePrefix . '.generate-csv') }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Download Template
    </a>

    {{-- 5. Export Master Data --}}
    <a href="{{ route($routePrefix . '.export') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Export Master Data
    </a>
</div>
@endsection

@section('content')
<div x-data="masterDataTable()">

    {{-- Info Bar --}}
    <div class="flex flex-wrap items-center gap-4 rounded-xl border px-5 py-3 mb-4 text-sm"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">Current Records:</span>
            <span class="font-bold" style="color: var(--epms-text);" x-text="$store.masterSap.currentRows">{{ number_format($totalRows) }}</span>
        </div>
        @if($hasSap ?? false)
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">New From SAP (staging):</span>
            <span class="font-bold" :class="$store.masterSap.newRows > 0 ? 'text-primary' : ''" style="color: var(--epms-text);" x-text="$store.masterSap.newRows">{{ number_format($newRows ?? 0) }}</span>
        </div>
        @endif
        @if($lastRefresh ?? false)
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">Last SAP Fetch:</span>
            <span class="font-medium" style="color: var(--epms-text);">{{ \Carbon\Carbon::parse($lastRefresh)->format('d/m/Y H:i') }}</span>
        </div>
        @endif
        @if($lastUpdate)
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">Last Refresh:</span>
            <span class="font-medium" style="color: var(--epms-text);">{{ \Carbon\Carbon::parse($lastUpdate)->format('d/m/Y H:i') }}</span>
        </div>
        @endif
        {{-- SAP result message --}}
        <div x-show="$store.masterSap.sapMessage" x-transition
             class="ml-auto px-3 py-1 rounded-lg text-xs font-medium"
             :class="$store.masterSap.sapSuccess ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
             x-text="$store.masterSap.sapMessage">
        </div>
    </div>

    {{-- Table Card --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="masterTable" class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10" style="color: var(--epms-text-muted);">#</th>
                            @foreach($columns as $col => $label)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        background: var(--epms-header-bg); color: var(--epms-text);
        border: 1px solid var(--epms-border); border-radius: 8px;
        padding: 4px 10px; font-size: 13px;
    }
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate { color: var(--epms-text-muted); font-size: 13px; margin-top: 12px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 6px !important; padding: 4px 10px !important; color: var(--epms-text) !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #5750f1 !important; border-color: #5750f1 !important; color: #fff !important; }
    table.dataTable thead th { border-bottom: 1px solid var(--epms-border) !important; }
    table.dataTable tbody td { padding: 10px 12px !important; vertical-align: middle; }
    table.dataTable.no-footer { border-bottom: none; }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
// ── Shared SAP store (accessible from both page-actions and content sections) ──
document.addEventListener('alpine:init', () => {
    Alpine.store('masterSap', {
        busy:        false,   // false | 'get' | 'refresh'
        sapMessage:  '',
        sapSuccess:  false,
        currentRows: {{ (int) $totalRows }},
        newRows:     {{ (int) ($newRows ?? 0) }},

        _csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

        _applyCounts(json) {
            if (json.data) {
                if (typeof json.data.current_rows !== 'undefined') this.currentRows = json.data.current_rows;
                if (typeof json.data.new_rows !== 'undefined')     this.newRows     = json.data.new_rows;
            }
        },

        _reloadTable() {
            if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#masterTable')) {
                $('#masterTable').DataTable().ajax.reload(null, false);
            }
        },

        // STEP 1 — pull SAP → staging
        async getFromSap() {
            if (this.busy) return;
            this.busy = 'get'; this.sapMessage = '';
            try {
                const res  = await fetch('{{ route($routePrefix . ".get-from-sap") }}', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': this._csrf() }
                });
                const json = await res.json();
                this.sapSuccess = json.success; this.sapMessage = json.message;
                this._applyCounts(json);
            } catch (e) {
                this.sapSuccess = false; this.sapMessage = 'Request failed: ' + e.message;
            } finally { this.busy = false; }
        },

        // STEP 2 — staging → master
        async refreshFromMaster() {
            if (this.busy) return;
            if (!confirm('Refresh {{ $resourceName }} master from SAP staging? Current data will be replaced.')) return;
            this.busy = 'refresh'; this.sapMessage = '';
            try {
                const res  = await fetch('{{ route($routePrefix . ".refresh-from-master") }}', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': this._csrf() }
                });
                const json = await res.json();
                this.sapSuccess = json.success; this.sapMessage = json.message;
                this._applyCounts(json);
                if (json.success) this._reloadTable();
            } catch (e) {
                this.sapSuccess = false; this.sapMessage = 'Request failed: ' + e.message;
            } finally { this.busy = false; }
        }
    });
});

// ── DataTable init (content scope) ─────────────────────────────────────────
function masterDataTable() {
    return {
        init() {
            const cols = @json(array_keys($columns));
            const dtCols = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
            cols.forEach(c => dtCols.push({ data: c, name: c, defaultContent: '-' }));

            $('#masterTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route($routePrefix . ".datatable") }}',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                },
                columns: dtCols,
                order: [[1, 'asc']],
                pageLength: 25,
                language: { processing: '<div class="py-4 text-center"><span class="loading loading-spinner loading-sm text-primary"></span></div>' },
            });
        }
    }
}
</script>
@endpush
