{{--
  Shared read-only monitoring DataTable. Variables:
    $title, $routePrefix, $columns (col => label), $from, $to
--}}
@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><span class="font-medium text-primary">{{ $title }}</span></li>
@endsection

@section('page-title', $title)
@section('page-subtitle', 'Review submitted records over a date range')

@section('content')
<div>
    {{-- Date range filter --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-5"
          style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">From</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">To</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">Show</button>
    </form>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="monitorTable" class="w-full text-sm" style="color: var(--epms-text);">
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
document.addEventListener('DOMContentLoaded', function () {
    const cols = @json(array_keys($columns));
    const dtCols = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
    cols.forEach(c => dtCols.push({ data: c, name: c, defaultContent: '-' }));

    const params = new URLSearchParams(window.location.search);
    $('#monitorTable').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: '{{ route($routePrefix.".datatable") }}',
            data: d => { d.from = params.get('from') || '{{ $from }}'; d.to = params.get('to') || '{{ $to }}'; },
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        },
        columns: dtCols, order: [[1,'desc']], pageLength: 25,
        language: { processing: '<div class="py-4 text-center"><span class="loading loading-spinner loading-sm text-primary"></span></div>' },
    });
});
</script>
@endpush
