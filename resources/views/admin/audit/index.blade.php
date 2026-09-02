@extends('layouts.app')

@section('title', 'Activity Log')

@section('breadcrumb')
    <li><span class="font-medium text-primary">Activity Log</span></li>
@endsection

@section('page-title', 'Activity Log')
@section('page-subtitle', 'System activity and audit trail')

@section('content')
<div x-data="auditTable()">

    {{-- Filters --}}
    <div class="rounded-xl border shadow-sm p-4 mb-4"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Transaction Type</label>
                <select id="filter_type" class="w-full rounded-lg border px-3 py-2 text-sm outline-none"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    <option value="">All Types</option>
                    @foreach($typeLabels as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Action</label>
                <select id="filter_action" class="w-full rounded-lg border px-3 py-2 text-sm outline-none"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    <option value="">All Actions</option>
                    @foreach($actionLabels as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Username</label>
                <input type="text" id="filter_user" placeholder="Search user..."
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none"
                       style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Date From</label>
                <input type="date" id="filter_date_from"
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none"
                       style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Date To</label>
                <input type="date" id="filter_date_to"
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none"
                       style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button @click="applyFilters()"
                    class="rounded-lg bg-primary px-4 py-2 text-xs font-medium text-white hover:opacity-90 transition">
                Apply Filters
            </button>
            <button @click="resetFilters()"
                    class="rounded-lg border px-4 py-2 text-xs font-medium transition hover:opacity-80"
                    style="border-color: var(--epms-border); color: var(--epms-text);">
                Reset
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="auditTable" class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10" style="color: var(--epms-text-muted);">#</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Date/Time</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Action</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">User</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Description</th>
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
let dtTable = null;

function auditTable() {
    return {
        init() {
            dtTable = $('#auditTable').DataTable({
                processing: true, serverSide: true,
                ajax: {
                    url: '{{ route("admin.audit.datatable") }}',
                    data: d => {
                        d.transaction_type = $('#filter_type').val();
                        d.action_type      = $('#filter_action').val();
                        d.user_code        = $('#filter_user').val();
                        d.date_from        = $('#filter_date_from').val();
                        d.date_to          = $('#filter_date_to').val();
                    },
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                },
                columns: [
                    { data: 'DT_RowIndex',         orderable: false, searchable: false, width: '40px' },
                    { data: 'created_at_fmt',       name: 'created_at' },
                    { data: 'transaction_type_label', name: 'transaction_type', orderable: false },
                    { data: 'action_type_label',    name: 'action_type',      orderable: false },
                    { data: 'user_code',            name: 'user_code' },
                    { data: 'description',          name: 'description',      orderable: false },
                ],
                order: [[1, 'desc']], pageLength: 25,
                language: { processing: '<div class="py-4 text-center"><span class="loading loading-spinner loading-sm text-primary"></span></div>' },
            });
        },
        applyFilters() { dtTable?.ajax.reload(); },
        resetFilters() {
            ['filter_type','filter_action','filter_user','filter_date_from','filter_date_to'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            dtTable?.ajax.reload();
        }
    }
}
</script>
@endpush
