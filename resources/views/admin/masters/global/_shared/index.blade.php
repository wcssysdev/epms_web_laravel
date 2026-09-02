@extends('layouts.app')

@section('title', $resourceName . ' (Global)')

@section('breadcrumb')
    <li><span class="font-medium text-primary">{{ $resourceName }}</span></li>
@endsection

@section('page-title', $resourceName)
@section('page-subtitle', 'Global lookup data — shared across all companies')

@section('page-actions')
<div class="flex items-center gap-2">
    <a href="{{ route($routePrefix . '.generate-csv') }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        CSV Template
    </a>
    <a href="{{ route($routePrefix . '.add') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add {{ $resourceName }}
    </a>
</div>
@endsection

@section('content')
<div x-data="globalLookupTable()">

    <div class="flex items-center gap-4 rounded-xl border px-5 py-3 mb-4 text-sm"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <span style="color: var(--epms-text-muted);">Total Records:</span>
        <span class="font-bold" style="color: var(--epms-text);">{{ number_format($totalRows) }}</span>
        <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">Global — Shared by all companies</span>
    </div>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="globalTable" class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10" style="color: var(--epms-text-muted);">#</th>
                            @foreach($columns as $col => $label)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</th>
                            @endforeach
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Actions</th>
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
    .dataTables_wrapper .dataTables_length select { background: var(--epms-header-bg); color: var(--epms-text); border: 1px solid var(--epms-border); border-radius: 8px; padding: 4px 10px; font-size: 13px; }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: var(--epms-text-muted); font-size: 13px; margin-top: 12px; }
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
function globalLookupTable() {
    return {
        init() {
            const cols = @json(array_keys($columns));
            const dtCols = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
            cols.forEach(c => dtCols.push({ data: c, name: c, defaultContent: '-' }));
            dtCols.push({
                data: null, orderable: false, searchable: false,
                render: (d, t, r) =>
                    `<div class="flex gap-1">
                        <a href="{{ url($routePrefix.'.edit', '') }}/${r.id || r.mhm_id || r.mvt_type_id}"
                           class="btn-action btn-edit" title="Edit" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                     </div>`
            });
            $('#globalTable').DataTable({
                processing: true, serverSide: true,
                ajax: { url: '{{ route($routePrefix.".datatable") }}', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } },
                columns: dtCols, order: [[1,'asc']], pageLength: 25,
            });
        }
    }
}
</script>
@endpush
