@extends('layouts.app')

@section('title', $resourceName)

@section('breadcrumb')
    <li><a href="home" class="text-gray-500 hover:text-primary">Home /</a></li>
    <li><span class="font-medium text-primary">{{ $resourceName }}</span></li>
@endsection

@section('page-title', $resourceName)
@section('page-subtitle', 'Manage ' . strtolower($resourceName) . ' data')

@section('page-actions')
<div class="flex flex-wrap items-center gap-2">
    @if($hasCsv ?? false)
    <a href="{{ Route::has($routePrefix . '.upload') ? route($routePrefix . '.upload') : '#' }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        Upload Data
    </a>

    {{-- Generate QR Bulk --}}
    <button type="button" @click="submitBulkQr()"
            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
            style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
        </svg>
        Generate QR
    </button>

    <a href="{{ Route::has($routePrefix . '.generate-csv') ? route($routePrefix . '.generate-csv') : '#' }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Download Template
    </a>
    <a href="{{ Route::has($routePrefix . '.export') ? route($routePrefix . '.export') : '#' }}"
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
<div x-data="tphTable()">

    {{-- Total Palm Warning Banner (matching CI3) --}}
    @if(!empty($totalPalmExceed))
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <h4 class="font-bold mb-2 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Warning! Sum of Total Palm exceeded from total palm of block. See data below:
        </h4>
        <div class="space-y-2 text-xs">
            @foreach($totalPalmExceed as $i => $tpc)
                <div class="border-b border-amber-200 pb-1">
                    <span class="font-semibold">[{{ $i + 1 }}] Block - [{{ $tpc->block_estate_code }}][{{ $tpc->block_division_code }}][{{ $tpc->block_code }}]</span>
                    <br>Total Palm of Master Block = <strong>{{ $tpc->block_total_palm }}</strong>
                    <br>Total Palm All Task under one Block = <strong class="text-red-600">{{ $tpc->ttl_plm }}</strong>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Info banner --}}
    <div class="flex flex-wrap items-center gap-6 rounded-xl border px-5 py-3 mb-4 text-sm"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <span style="color: var(--epms-text-muted);">Current Records:</span>
            <span class="font-bold" style="color: var(--epms-text);">{{ number_format($totalRows) }}</span>
        </div>
    </div>

    {{-- Hidden form for bulk QR submission --}}
    <form id="bulkQrForm" method="POST" action="{{ route($routePrefix . '.generate-qr') }}" target="_blank" class="hidden">
        @csrf
        <div id="bulkQrInputs"></div>
    </form>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table id="tphTable" class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10"
                                style="color: var(--epms-text-muted);">
                                <input type="checkbox" id="check_all" class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4 cursor-pointer">
                            </th>
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

    {{-- Delete confirmation modal --}}
    <div x-show="showDelete" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="showDelete = false">
        <div class="rounded-2xl border bg-white dark:bg-gray-900 p-6 shadow-xl max-w-sm w-full space-y-4"
             style="border-color: var(--epms-border);" @click.outside="showDelete = false">
            <div class="flex items-center gap-3 text-red-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h3 class="font-semibold text-base" style="color: var(--epms-text);">Delete {{ $resourceName }}</h3>
            </div>
            <p class="text-sm" style="color: var(--epms-text-muted);">
                Are you sure you want to delete this record? This action cannot be undone.
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showDelete = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium border hover:bg-gray-50 transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">
                    Cancel
                </button>
                <form :action="deleteUrl" method="POST">
                    @csrf
                    @method('DELETE')
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

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
function tphTable() {
    return {
        showDelete: false,
        deleteUrl:  '',
        init() {
            window.confirmTphDelete = (url) => {
                this.confirmDelete(url);
            };

            const cols = @json(array_keys($columns));
            const editBase = '{{ url(str_replace(".", "/", $routePrefix)) }}';
            const hasQr    = {{ ($hasQr ?? false) ? 'true' : 'false' }};
            const hasEdit  = {{ ($hasEdit ?? false) ? 'true' : 'false' }};

            const dtCols = [
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    width: '40px',
                    render: (id) => {
                        return `<input type="checkbox" class="task_checkbox rounded border-gray-300 text-primary focus:ring-primary h-4 w-4 cursor-pointer" name="tph_id[]" value="${id}">`;
                    }
                }
            ];

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
                        <button onclick="window.confirmTphDelete('${editBase}/${r.id}')"
                                class="btn-action btn-danger" title="Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>`;
                }
            });

            const table = $('#tphTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route($routePrefix.".datatable") }}',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                },
                columns: dtCols,
                order: [[1, 'asc']],
                pageLength: 25,
                language: { processing: '<div class="py-4 text-center"><span class="loading loading-spinner loading-sm text-primary"></span></div>' },
            });

            // Handle check all checkbox
            $('#check_all').on('click', function() {
                $('.task_checkbox').prop('checked', this.checked);
            });

            // Handle uncheck of check_all if any row is unchecked
            $(document).on('change', '.task_checkbox', function() {
                if (!this.checked) {
                    $('#check_all').prop('checked', false);
                } else if ($('.task_checkbox:checked').length === $('.task_checkbox').length) {
                    $('#check_all').prop('checked', true);
                }
            });
        },
        confirmDelete(url) {
            this.deleteUrl = url;
            this.showDelete = true;
        },
        submitBulkQr() {
            const checked = $('.task_checkbox:checked');
            if (checked.length === 0) {
                alert('Please select at least one task to generate QR');
                return;
            }

            const container = document.getElementById('bulkQrInputs');
            container.innerHTML = '';
            checked.each(function() {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tph_id[]';
                input.value = this.value;
                container.appendChild(input);
            });

            document.getElementById('bulkQrForm').submit();
        }
    }
}
</script>
@endpush
