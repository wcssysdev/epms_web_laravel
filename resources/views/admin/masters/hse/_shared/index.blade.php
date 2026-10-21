{{--
    Shared index view for the 10 portal-synced HSE (Master SHE) masters.

    This is the HSE Portal THREE-STEP analogue of resources/views/admin/masters/_shared/index.blade.php
    (which is SAP-specific). Each of the 10 per-master index.blade.php files is a one-liner that
    @includes this partial, mirroring how admin/masters/vra/index.blade.php includes the SAP shared view.

    Data contract (supplied by BaseMasterController::index() → view($viewPrefix.'.index', [...]):
      - $resourceName : human label, e.g. "Category of Injuries"
      - $routePrefix  : route name prefix, e.g. "masters.hse.injuries_category"
      - $columns      : [db_column => label] rendered as DataTable columns
      - $totalRows    : current live-master row count
      - $newRows      : staged row count (HSE staging mirror)
      - $lastUpdate / $lastRefresh : optional timestamps (may be null)

    HSE masters are portal-synced READ-ONLY: NO "Add" button (like VRA for SAP).
    Three-step flow routes (registered in task 9.1):
      - {routePrefix}.get-from-hse        (POST)  STEP 1
      - {routePrefix}.staging-info        (GET)   STEP 2
      - {routePrefix}.replace-from-staging(POST)  STEP 3
    Plus the inherited CSV/upload/export/datatable/destroy routes.
--}}
@extends('layouts.app')

@section('title', $resourceName . ' (Master SHE)')

@section('breadcrumb')
    <li><span style="color: var(--epms-text-muted);">Master SHE</span></li>
    <li><span class="font-medium text-primary">{{ $resourceName }}</span></li>
@endsection

@section('page-title', $resourceName)
@section('page-subtitle', 'Master SHE — portal-synced (read-only) · ' . strtolower($resourceName) . ' master data')

@section('page-actions')
<div class="flex flex-wrap items-center gap-2">
    {{-- 1. Get All Data From HSE (STEP 1) --}}
    <button type="button" @click="$store.masterHse.getFromHse()" :disabled="$store.masterHse.busy"
            class="cursor-pointer flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80 disabled:opacity-50"
            style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': $store.masterHse.busy==='get' }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Get All Data From HSE
    </button>

    {{-- 2. Refresh Master Data Info (STEP 2 → opens Replace confirmation modal) --}}
    <button type="button" @click="$store.masterHse.openReplaceModal()" :disabled="$store.masterHse.busy"
            class="cursor-pointer flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80 disabled:opacity-50"
            style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': $store.masterHse.busy==='info' }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Refresh Master Data Info
    </button>

    {{-- 3. Upload Data (CSV upload — inherited BaseMasterController::upload) --}}
    <a href="{{ Route::has($routePrefix . '.upload') ? route($routePrefix . '.upload') : '#' }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        Upload Data
    </a>

    {{-- 4. Download Template (empty CSV with headers — inherited generateCsv) --}}
    <a href="{{ Route::has($routePrefix . '.generate-csv') ? route($routePrefix . '.generate-csv') : '#' }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Download Template
    </a>

    {{-- 5. Export Master Data (CSV with values — inherited exportMasterData) --}}
    <a href="{{ Route::has($routePrefix . '.export') ? route($routePrefix . '.export') : '#' }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Export Master Data
    </a>
</div>
@endsection

@section('content')
<div x-data="hseMasterDataTable()">

    {{-- Flash messages (Replace is a form-post redirect → flash success/error) --}}
    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 mb-4 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 mb-4 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Info Bar --}}
    <div class="flex flex-wrap items-center gap-4 rounded-xl border px-5 py-3 mb-4 text-sm"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">Current Records:</span>
            <span class="font-bold" style="color: var(--epms-text);" x-text="$store.masterHse.currentRows">{{ number_format($totalRows) }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">New From HSE (staging):</span>
            <span class="font-bold" :class="$store.masterHse.newRows > 0 ? 'text-primary' : ''" style="color: var(--epms-text);" x-text="$store.masterHse.newRows">{{ number_format($newRows ?? 0) }}</span>
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">Global — Shared by all companies</span>
        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Portal-synced · Read-only</span>
        @if($lastRefresh ?? false)
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">Last HSE Fetch:</span>
            <span class="font-medium" style="color: var(--epms-text);">{{ \Carbon\Carbon::parse($lastRefresh)->format('d/m/Y H:i') }}</span>
        </div>
        @endif
        @if($lastUpdate ?? false)
        <div class="flex items-center gap-2">
            <span style="color: var(--epms-text-muted);">Last Replace:</span>
            <span class="font-medium" style="color: var(--epms-text);">{{ \Carbon\Carbon::parse($lastUpdate)->format('d/m/Y H:i') }}</span>
        </div>
        @endif
        {{-- Live AJAX result message (Get / Refresh Info) --}}
        <div x-show="$store.masterHse.hseMessage" x-transition
             class="ml-auto px-3 py-1 rounded-lg text-xs font-medium"
             :class="$store.masterHse.hseSuccess ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
             x-text="$store.masterHse.hseMessage">
        </div>
    </div>

    {{-- Replace Master Data confirmation modal (STEP 3) --}}
    <div x-show="$store.masterHse.showReplaceModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="$store.masterHse.showReplaceModal = false"></div>
        <div class="relative w-full max-w-md rounded-xl border shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h3 class="text-base font-bold" style="color: var(--epms-text);">Replace {{ $resourceName }} Master Data</h3>
                <button @click="$store.masterHse.showReplaceModal = false" class="cursor-pointer text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-5 py-5 text-sm" style="color: var(--epms-text);">
                <div class="space-y-1.5 mb-4">
                    <div class="flex justify-between gap-4">
                        <span style="color: var(--epms-text-muted);">Current Data on Local Server</span>
                        <span class="font-medium">: <span x-text="$store.masterHse.currentRows"></span> data</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span style="color: var(--epms-text-muted);">Latest Data From HSE (staging)</span>
                        <span class="font-bold" style="color: var(--epms-text);">: <span x-text="$store.masterHse.newRows"></span> data</span>
                    </div>
                </div>
                <p style="color: var(--epms-text-muted);">Are you sure you want to replace current data with the staged HSE data? This cannot be undone.</p>
                <template x-if="$store.masterHse.newRows === 0">
                    <p class="mt-2 text-xs text-amber-600">No staged data available. Run "Get All Data From HSE" first.</p>
                </template>
            </div>
            <div class="flex justify-end gap-3 px-5 py-4 border-t" style="border-color: var(--epms-border);">
                <button @click="$store.masterHse.showReplaceModal = false"
                        class="cursor-pointer flex items-center gap-1.5 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Cancel
                </button>
                {{-- STEP 3 is a state-changing POST → real CSRF-protected form submit (not fetch) --}}
                <form method="POST"
                      action="{{ Route::has($routePrefix . '.replace-from-staging') ? route($routePrefix . '.replace-from-staging') : '#' }}">
                    @csrf
                    <button type="submit"
                            :disabled="$store.masterHse.newRows === 0"
                            class="cursor-pointer flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Yes, Replace Master Data
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="hseMasterTable" class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10" style="color: var(--epms-text-muted);">#</th>
                            @foreach($columns as $col => $label)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</th>
                            @endforeach
                            @if(Route::has($routePrefix . '.destroy'))
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Delete-with-confirmation: CSRF-protected state-changing request --}}
    @if(Route::has($routePrefix . '.destroy'))
    <form id="hseDeleteForm" method="POST" action="" class="hidden">
        @csrf
        @method('DELETE')
    </form>
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
    [x-cloak] { display: none !important; }
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
// ── Route URLs resolved server-side (guarded so missing routes degrade gracefully) ──
const HSE_ROUTES = {
    getFromHse:  '{{ Route::has($routePrefix . ".get-from-hse") ? route($routePrefix . ".get-from-hse") : "" }}',
    stagingInfo: '{{ Route::has($routePrefix . ".staging-info") ? route($routePrefix . ".staging-info") : "" }}',
    datatable:   '{{ Route::has($routePrefix . ".datatable") ? route($routePrefix . ".datatable") : "" }}',
};

// ── Shared HSE store (accessible from page-actions and content sections) ──
document.addEventListener('alpine:init', () => {
    Alpine.store('masterHse', {
        busy:            false,   // false | 'get' | 'info'
        hseMessage:      '',
        hseSuccess:      false,
        currentRows:     {{ (int) $totalRows }},
        newRows:         {{ (int) ($newRows ?? 0) }},
        showReplaceModal: false,

        _csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

        _applyCounts(json) {
            if (json && json.data) {
                if (typeof json.data.current_rows !== 'undefined') this.currentRows = json.data.current_rows;
                if (typeof json.data.new_rows !== 'undefined')     this.newRows     = json.data.new_rows;
            }
        },

        _reloadTable() {
            if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#hseMasterTable')) {
                $('#hseMasterTable').DataTable().ajax.reload(null, false);
            }
        },

        // STEP 1 — pull HSE Portal → staging
        async getFromHse() {
            if (this.busy || !HSE_ROUTES.getFromHse) return;
            this.busy = 'get'; this.hseMessage = '';
            try {
                const res  = await fetch(HSE_ROUTES.getFromHse, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' }
                });
                const json = await res.json();
                this.hseSuccess = json.success; this.hseMessage = json.message;
                this._applyCounts(json);
            } catch (e) {
                this.hseSuccess = false; this.hseMessage = 'Request failed: ' + e.message;
            } finally { this.busy = false; }
        },

        // STEP 2 — Refresh Master Data Info: refresh counts, then open Replace modal
        async openReplaceModal() {
            if (this.busy) return;
            this.busy = 'info'; this.hseMessage = '';
            try {
                if (HSE_ROUTES.stagingInfo) {
                    const res  = await fetch(HSE_ROUTES.stagingInfo, {
                        headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' }
                    });
                    const json = await res.json();
                    this._applyCounts(json);
                }
            } catch (e) { /* keep existing counts */ }
            finally {
                this.busy = false;
                this.showReplaceModal = true;
            }
        }
        // STEP 3 — "Replace Master Data" is a CSRF-protected form POST inside the modal.
    });
});

// ── DataTable init (content scope) ─────────────────────────────────────────
function hseMasterDataTable() {
    return {
        init() {
            const cols        = @json(array_keys($columns));
            const hasDelete   = {{ Route::has($routePrefix . '.destroy') ? 'true' : 'false' }};
            @php
                // Natural key used to build the destroy URL per row. Prefer the primary/business key.
                $deleteKey = array_key_first($columns);
            @endphp
            const deleteKey   = @json($deleteKey);
            const deleteBase  = hasDelete ? "{{ Route::has($routePrefix . '.destroy') ? url($routePrefix . '.destroy', '') : '' }}" : '';

            const dtCols = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
            cols.forEach(c => {
                if (c === 'is_active') {
                    // Render is_active as a status badge.
                    dtCols.push({
                        data: c, name: c, defaultContent: '-',
                        render: (d) => {
                            const active = (d === 1 || d === '1' || d === true || d === 'true' || d === 't');
                            return active
                                ? '<span style="display:inline-block;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;background:#dcfce7;color:#15803d;">Active</span>'
                                : '<span style="display:inline-block;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;">Inactive</span>';
                        }
                    });
                } else {
                    dtCols.push({ data: c, name: c, defaultContent: '-' });
                }
            });

            if (hasDelete) {
                dtCols.push({
                    data: null, orderable: false, searchable: false,
                    render: (d, t, r) => {
                        const rawKey = (r[deleteKey] !== undefined && r[deleteKey] !== null) ? String(r[deleteKey]) : '';
                        const key    = encodeURIComponent(rawKey);
                        const label  = rawKey.replace(/'/g, "\\'");
                        return `<div class="flex gap-1">
                            <button type="button" title="Delete"
                                    onclick="confirmHseDelete('${deleteBase}/${key}', '${label}')"
                                    style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;border:1px solid #fecaca;background:#fef2f2;color:#dc2626;cursor:pointer;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>`;
                    }
                });
            }

            $('#hseMasterTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: HSE_ROUTES.datatable,
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

// Delete-with-confirmation: explicit confirm, then CSRF-protected DELETE submit.
function confirmHseDelete(actionUrl, label) {
    if (!actionUrl) return;
    if (!window.confirm('Delete "' + label + '"? This cannot be undone.')) {
        return;
    }
    const form = document.getElementById('hseDeleteForm');
    if (!form) return;
    form.setAttribute('action', actionUrl);
    form.submit();
}
</script>
@endpush
