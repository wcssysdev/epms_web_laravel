@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><span class="font-medium text-primary">{{ $title }}</span></li>
@endsection

@section('page-title', $title)
@section('page-subtitle', 'Read-only mill grader records (captured on mobile)')

@section('content')
<div>
    {{-- Date range filter --}}
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
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">Filter</button>
    </form>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="mgTable" class="w-full text-sm" style="color: var(--epms-text);">
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
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cols   = @json(array_keys($columns));
    const dtCols = [{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' }];
    cols.forEach(c => dtCols.push({ data: c, name: c, defaultContent: '-' }));
    $('#mgTable').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: '{{ route("transactions.oph_mill_grader.datatable") }}',
            data: (d) => { d.from = '{{ $from }}'; d.to = '{{ $to }}'; },
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        },
        columns: dtCols, order: [], pageLength: 25,
    });
});
</script>
@endpush
