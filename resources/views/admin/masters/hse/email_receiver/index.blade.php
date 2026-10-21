@extends('layouts.app')

@section('title', $resourceName)

@section('breadcrumb')
    <li><span class="font-medium text-primary">{{ $resourceName }}</span></li>
@endsection

@section('page-title', $resourceName)
@section('page-subtitle', 'HSE email notification recipients')

@section('page-actions')
<div class="flex items-center gap-2">
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
<div x-data="emailReceiverTable()">

    <div class="flex items-center gap-4 rounded-xl border px-5 py-3 mb-4 text-sm"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <span style="color: var(--epms-text-muted);">Total Records:</span>
        <span class="font-bold" style="color: var(--epms-text);">{{ number_format($totalRows) }}</span>
    </div>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="emailReceiverTable" class="w-full text-sm" style="color: var(--epms-text);">
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

    {{-- Delete confirmation form (CSRF + DELETE method) --}}
    <form id="deleteForm" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>
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
function emailReceiverTable() {
    return {
        init() {
            const cols = @json(array_keys($columns));
            const dtCols = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
            cols.forEach(c => {
                if (c === 'receiver_is_active') {
                    dtCols.push({
                        data: c, name: c, defaultContent: '-',
                        render: v => Number(v) === 1
                            ? '<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Active</span>'
                            : '<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Inactive</span>'
                    });
                } else {
                    dtCols.push({ data: c, name: c, defaultContent: '-' });
                }
            });
            const editUrlTemplate = '{{ route($routePrefix.'.edit', ['id' => '__ID__']) }}';
            const destroyUrlBase  = '{{ route($routePrefix.'.index') }}'.replace(/\/$/, '');
            dtCols.push({
                data: null, orderable: false, searchable: false,
                render: (d, t, r) =>
                    `<div class="flex gap-1">
                        <a href="${editUrlTemplate.replace('__ID__', r.id)}"
                           class="btn-action btn-edit" title="Edit" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <button type="button" onclick="confirmDeleteReceiver(${r.id})"
                           class="btn-action btn-delete" title="Delete" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;border:1px solid #fecaca;background:#fef2f2;color:#dc2626;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                     </div>`
            });
            $('#emailReceiverTable').DataTable({
                processing: true, serverSide: true,
                ajax: { url: '{{ route($routePrefix.".datatable") }}', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } },
                columns: dtCols, order: [[1,'asc']], pageLength: 25,
            });
        }
    }
}

function confirmDeleteReceiver(id) {
    if (!confirm('Delete this Email Receiver?')) return;
    const form = document.getElementById('deleteForm');
    // destroy URL = {index base}/{id}  — matches DELETE email-receiver/{id} (9.1)
    const base = '{{ route($routePrefix.'.index') }}'.replace(/\/$/, '');
    form.action = base + '/' + id;
    form.submit();
}
</script>
@endpush
