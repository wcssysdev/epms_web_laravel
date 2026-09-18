@extends('layouts.app')

@section('title', $resourceName)

@section('breadcrumb')
    <li><span class="font-medium text-primary">{{ $resourceName }}</span></li>
@endsection

@section('page-title', $resourceName)
@section('page-subtitle', 'Manage ' . strtolower($resourceName) . ' assignments')

@section('page-actions')
<div class="flex flex-wrap items-center gap-2">
    @if($hasSap ?? false)
    @if($hasGetFromSap ?? false)
    {{-- 1. Get All Data From SAP --}}
    <button type="button" @click="$store.masterSap.getFromSap()" :disabled="$store.masterSap.busy"
            class="cursor-pointer flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80 disabled:opacity-50"
            style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': $store.masterSap.busy==='get' }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Get All Data From SAP
    </button>
    @endif

    {{-- 2. Refresh Master Data From SAP --}}
    <button type="button" @click="$store.masterSap.openRefreshModal()" :disabled="$store.masterSap.busy"
            class="cursor-pointer flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80 disabled:opacity-50"
            style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': $store.masterSap.busy==='refresh' }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Refresh Master Data From SAP
    </button>
    @endif

    @if($hasCsv ?? false)
    <a href="{{ route($routePrefix . '.upload') }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        Upload Data
    </a>
    <a href="{{ route($routePrefix . '.generate-csv') }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Download Template
    </a>
    <a href="{{ route($routePrefix . '.export') }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Export Master Data
    </a>
    @endif
    <a href="{{ route($routePrefix . '.create') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add {{ $resourceName }}
    </a>
</div>
@endsection

@section('content')
<div x-data="groupingTable()">

    {{-- SAP sync message banner --}}
    <div x-show="$store.masterSap.sapMessage" x-cloak class="mb-4 rounded-xl border p-4 text-sm"
         :class="$store.masterSap.sapSuccess ? 'border-green-300 bg-green-50 text-green-800' : 'border-red-300 bg-red-50 text-red-800'">
        <div class="flex items-center justify-between">
            <span x-text="$store.masterSap.sapMessage"></span>
            <button @click="$store.masterSap.sapMessage = ''" class="text-xs font-semibold uppercase opacity-60 hover:opacity-100">Dismiss</button>
        </div>
    </div>

    {{-- Info banner --}}
    <div class="flex flex-wrap items-center gap-6 rounded-xl border px-5 py-3 mb-4 text-sm"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <span style="color: var(--epms-text-muted);">Current Records:</span>
            <span class="font-bold" style="color: var(--epms-text);" x-text="$store.masterSap.currentRows"></span>
        </div>
        @if($hasSap ?? false)
        <div>
            <span style="color: var(--epms-text-muted);">New From SAP (staging):</span>
            <span class="font-bold text-primary" x-text="$store.masterSap.newRows"></span>
        </div>
        @endif
    </div>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="groupingTable" class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10"
                                style="color: var(--epms-text-muted);">#</th>
                            @foreach($columns as $col => $label)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color: var(--epms-text-muted);">{{ $label }}</th>
                            @endforeach
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color: var(--epms-text-muted);">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Replace Master Data confirmation modal --}}
    <div x-show="$store.masterSap.showRefreshModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="$store.masterSap.showRefreshModal = false"></div>
        <div class="relative w-full max-w-md rounded-xl border shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h3 class="text-base font-bold" style="color: var(--epms-text);">Replace {{ $resourceName }} Data Confirmation</h3>
                <button @click="$store.masterSap.showRefreshModal = false" class="cursor-pointer text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-5 text-sm" style="color: var(--epms-text);">
                <div class="rounded-lg border p-4 mb-4 space-y-2"
                     style="border-color: var(--epms-border); background: var(--epms-bg);">
                    <div class="flex justify-between gap-4">
                        <span style="color: var(--epms-text-muted);">Current Data</span>
                        <span class="font-medium">: <span x-text="$store.masterSap.currentRows"></span> data</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span style="color: var(--epms-text-muted);">Latest Data Available</span>
                        <span class="font-bold" style="color: var(--epms-text);">: <span x-text="$store.masterSap.newRows"></span> data</span>
                    </div>
                </div>
                <p style="color: var(--epms-text-muted);">Are you sure you want to replace current data with the latest data from SAP?</p>
                <template x-if="$store.masterSap.newRows === 0">
                    <p class="mt-2 text-xs text-amber-600">No staged data available. Run "Get All Data From SAP" first if available.</p>
                </template>
            </div>
            <div class="flex justify-end gap-3 px-5 py-4 border-t" style="border-color: var(--epms-border);">
                <button @click="$store.masterSap.showRefreshModal = false"
                        class="cursor-pointer flex items-center gap-1.5 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Cancel
                </button>
                <button @click="$store.masterSap.confirmRefresh()"
                        :disabled="$store.masterSap.busy || $store.masterSap.newRows === 0"
                        class="cursor-pointer flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': $store.masterSap.busy==='refresh' }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Yes, Replace Master Data
                </button>
            </div>
        </div>
    </div>

    {{-- Confirm Delete Modal --}}
    <div x-show="showDelete" class="fixed inset-0 z-50 flex items-center justify-center"
         x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showDelete = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-base font-bold mb-2" style="color: var(--epms-text);">Confirm Delete</h3>
            <p class="text-sm mb-5" style="color: var(--epms-text-muted);">
                Delete this {{ strtolower($resourceName) }} record? This cannot be undone.
            </p>
            <div class="flex gap-3 justify-end">
                <button @click="showDelete = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</button>
                <form :action="deleteUrl" method="POST" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select { background: var(--epms-header-bg); color: var(--epms-text); border: 1px solid var(--epms-border); border-radius: 8px; padding: 4px 10px; font-size: 13px; }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: var(--epms-text-muted); font-size: 13px; margin-top: 12px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 6px !important; padding: 4px 10px !important; color: var(--epms-text) !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #5750f1 !important; border-color: #5750f1 !important; color: #fff !important; }
    table.dataTable thead th { border-bottom: 1px solid var(--epms-border) !important; }
    table.dataTable tbody td { padding: 10px 12px !important; vertical-align: middle; }
    table.dataTable.no-footer { border-bottom: none; }
    .btn-action { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; border:1px solid; transition:opacity 0.15s; cursor:pointer; }
    .btn-action:hover { opacity: 0.75; }
    .btn-edit { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
    .btn-danger { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
    .btn-qr { background:#f0fdf4; border-color:#bbf7d0; color:#16a34a; }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('masterSap', {
        busy:            false,   // false | 'get' | 'refresh'
        sapMessage:      '',
        sapSuccess:      false,
        currentRows:     {{ (int) $totalRows }},
        newRows:         {{ (int) ($newRows ?? 0) }},
        showRefreshModal: false,

        _csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

        _applyCounts(json) {
            if (json.data) {
                if (typeof json.data.current_rows !== 'undefined') this.currentRows = json.data.current_rows;
                if (typeof json.data.new_rows !== 'undefined')     this.newRows     = json.data.new_rows;
            }
        },

        _reloadTable() {
            if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#groupingTable')) {
                $('#groupingTable').DataTable().ajax.reload(null, false);
            }
        },

        // STEP 1 — pull SAP into staging
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

        // STEP 2 — open confirmation modal
        async openRefreshModal() {
            if (this.busy) return;
            this.sapMessage = '';
            try {
                const res  = await fetch('{{ route($routePrefix . ".staging-info") }}', {
                    headers: { 'X-CSRF-TOKEN': this._csrf() }
                });
                const json = await res.json();
                this._applyCounts(json);
            } catch (e) { }
            this.showRefreshModal = true;
        },

        // STEP 2 — confirmed: staging -> master
        async confirmRefresh() {
            if (this.busy || this.newRows === 0) return;
            this.busy = 'refresh'; this.sapMessage = '';
            try {
                const res  = await fetch('{{ route($routePrefix . ".refresh-from-sap") }}', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': this._csrf() }
                });
                const json = await res.json();
                this.sapSuccess = json.success; this.sapMessage = json.message;
                this._applyCounts(json);
                if (json.success) {
                    this.showRefreshModal = false;
                    this._reloadTable();
                }
            } catch (e) {
                this.sapSuccess = false; this.sapMessage = 'Refresh failed: ' + e.message;
            } finally { this.busy = false; }
        },
    });
});

function groupingTable() {
    return {
        showDelete: false,
        deleteUrl:  '',
        init() {
            window.confirmGroupingDelete = (url) => {
                this.confirmDelete(url);
            };
            const cols = @json(array_keys($columns));
            const editBase  = '{{ url(str_replace(".", "/", $routePrefix)) }}';
            const hasQr     = {{ ($hasQr ?? false) ? 'true' : 'false' }};
            const hasEdit   = {{ ($hasEdit ?? false) ? 'true' : 'false' }};
            const dtCols = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
            cols.forEach(c => dtCols.push({ data: c, name: c, defaultContent: '-' }));
            dtCols.push({
                data: null, orderable: false, searchable: false,
                render: (d, t, r) => {
                    const editBtn = hasEdit ? `
                        <a href="${editBase}/${r.id}/edit" class="btn-action btn-edit" title="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>` : '';
                    const qrBtn = hasQr ? `
                        <button onclick="window.open('${editBase}/${r.id}/print-qr', '_blank', 'width=420,height=560')"
                                class="btn-action btn-qr" title="Print QR">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 3h3m-3 3h6m0-6v6"/>
                            </svg>
                        </button>` : '';
                    return `
                    <div class="flex gap-1">
                        ${editBtn}
                        ${qrBtn}
                        <button onclick="window.confirmGroupingDelete('${editBase}/${r.id}')"
                                class="btn-action btn-danger" title="Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>`;
                }
            });

            $('#groupingTable').DataTable({
                processing: true, serverSide: true,
                ajax: { url: '{{ route($routePrefix.".datatable") }}', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } },
                columns: dtCols, order: [[1,'asc']], pageLength: 25,
                language: { processing: '<div class="py-4 text-center"><span class="loading loading-spinner loading-sm text-primary"></span></div>' },
            });
        },
        confirmDelete(url) {
            this.deleteUrl  = url;
            this.showDelete = true;
        }
    }
}
</script>
@endpush
