@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><span class="font-medium text-primary">{{ $title }}</span></li>
@endsection

@section('page-title', $title)
@section('page-subtitle', 'Grade coconut harvesting chits already added to a checkpoint')

@section('content')
<div x-data="gradingTable()">

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-4"
          style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">From</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">To</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <button type="submit"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
            Filter
        </button>
    </form>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="gradingTable" class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10" style="color: var(--epms-text-muted);">#</th>
                            @foreach($columns as $col => $label)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</th>
                            @endforeach
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Graded</th>
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
    [x-cloak] { display: none !important; }
    .dataTables_wrapper .dataTables_filter input, .dataTables_wrapper .dataTables_length select { background: var(--epms-header-bg); color: var(--epms-text); border: 1px solid var(--epms-border); border-radius: 8px; padding: 4px 10px; font-size: 13px; }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: var(--epms-text-muted); font-size: 13px; margin-top: 12px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #5750f1 !important; border-color: #5750f1 !important; color: #fff !important; }
    table.dataTable tbody td { padding: 10px 12px !important; vertical-align: middle; }
    .btn-action { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; border:1px solid; transition:opacity 0.15s; cursor:pointer; }
    .btn-action:hover { opacity: 0.75; }
    .btn-edit { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
function gradingTable() {
    return {
        init() {
            const cols     = @json(array_keys($columns));
            const editBase = '{{ url(str_replace(".", "/", $routePrefix)) }}';
            const dtCols   = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
            cols.forEach(c => dtCols.push({ data: c, name: c, defaultContent: '-' }));
            dtCols.push({ data: 'grading_count', name: 'grading_count', orderable: false, searchable: false, defaultContent: '0' });
            dtCols.push({
                data: null, orderable: false, searchable: false,
                render: (d, t, r) => `
                    <a href="${editBase}/${r.id}/edit" class="btn-action btn-edit" title="Grade">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>`
            });

            $('#gradingTable').DataTable({
                processing: true, serverSide: true,
                ajax: {
                    url: '{{ route($routePrefix.".datatable") }}',
                    data: (d) => { d.from = '{{ $from }}'; d.to = '{{ $to }}'; },
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                },
                columns: dtCols, order: [], pageLength: 25,
            });
        }
    }
}
</script>
@endpush
