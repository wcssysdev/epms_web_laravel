@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><span class="font-medium text-primary">Closing / {{ $title }}</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'SAP adjustment audit log')

@section('content')
<div x-data="{}">

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-4"
          style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">From</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">To</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Type</label>
            <select name="type" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                    style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
                @foreach($types as $t)
                    <option value="{{ $t }}" @selected($selType===$t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">Filter</button>
    </form>

    {{-- DataTable --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="p-5">
            <table id="adjTable" class="w-full text-sm" style="color:var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color:var(--epms-border);">
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10"
                            style="color:var(--epms-text-muted);">#</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Time</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Type</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Transaction ID</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Tx Date</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Note</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Adjusted By</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
.dataTables_wrapper .dataTables_filter input,.dataTables_wrapper .dataTables_length select{background:var(--epms-header-bg);color:var(--epms-text);border:1px solid var(--epms-border);border-radius:8px;padding:4px 10px;font-size:13px;}
.dataTables_wrapper .dataTables_info,.dataTables_wrapper .dataTables_paginate{color:var(--epms-text-muted);font-size:13px;margin-top:12px;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:#5750f1!important;border-color:#5750f1!important;color:#fff!important;}
table.dataTable tbody td{padding:10px 12px!important;vertical-align:middle;}
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
$('#adjTable').DataTable({
    processing: true, serverSide: true,
    ajax: {
        url: '{{ route("closing.adjustment.datatable") }}',
        data: d => {
            d.from = '{{ $from }}';
            d.to   = '{{ $to }}';
            d.type = '{{ $selType }}';
        },
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    },
    columns: [
        { data:'DT_RowIndex', orderable:false, searchable:false, width:'40px' },
        { data:'date',              defaultContent:'-' },
        { data:'time',              defaultContent:'-' },
        { data:'adjustment_type',   defaultContent:'-' },
        { data:'global_id',         defaultContent:'-' },
        { data:'transaction_date',  defaultContent:'-' },
        { data:'note',              defaultContent:'-' },
        { data:'ajust_by',          defaultContent:'-' },
    ],
    order:[[1,'desc']], pageLength:25,
});
</script>
@endpush
