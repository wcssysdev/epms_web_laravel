@extends('layouts.app')

@section('title', 'HIRARC Template')

@section('breadcrumb')
    <li><span class="font-medium text-primary">Master SHE — HIRARC Template</span></li>
@endsection

@section('page-title', 'HIRARC Template')
@section('page-subtitle', 'Fetch HIRARC templates from the HSE Portal into staging (fetch-only)')

@section('content')
<div x-data="hirarcTemplate()">

    {{-- Action bar --}}
    <div class="rounded-xl border shadow-sm p-4 mb-4 flex flex-wrap items-center gap-2"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">

        {{-- Get Template (POST) --}}
        <form method="POST" action="{{ route('masters.hse.hirarc_template.get-template') }}"
              onsubmit="return confirm('Fetch HIRARC templates from the HSE Portal? This truncates and reloads the staging table.');">
            @csrf
            <button type="submit"
                    class="rounded-lg bg-primary px-4 py-2 text-xs font-medium text-white hover:opacity-90 transition">
                Get Template From HSE
            </button>
        </form>

        {{-- Refresh Info (GET via AJAX) --}}
        <button @click="refreshInfo()" :disabled="loading"
                class="rounded-lg border px-4 py-2 text-xs font-medium transition hover:opacity-80 disabled:opacity-50"
                style="border-color: var(--epms-border); color: var(--epms-text);">
            <span x-show="!loading">Refresh Info</span>
            <span x-show="loading">Refreshing…</span>
        </button>

        <span class="ml-auto text-xs" style="color: var(--epms-text-muted);">
            Apply-to-transaction (write into <code>t_hirarc</code>) is out of scope — deferred to the HIRARC transaction spec.
        </span>
    </div>

    {{-- Compare counts --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div class="rounded-xl border shadow-sm p-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--epms-text-muted);">
                Current (t_hirarc)
            </div>
            <div class="text-2xl font-bold" style="color: var(--epms-text);">
                @if($currentExists)
                    <span x-text="currentRows">{{ $currentRows }}</span>
                @else
                    <span class="text-base font-medium" style="color: var(--epms-text-muted);">n/a — table not present</span>
                @endif
            </div>
        </div>
        <div class="rounded-xl border shadow-sm p-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--epms-text-muted);">
                Staged (zhse_hirarc_template)
            </div>
            <div class="text-2xl font-bold" style="color: var(--epms-text);">
                <span x-text="newRows">{{ $newRows }}</span>
            </div>
        </div>
    </div>

    {{-- Staged rows listing --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide w-10" style="color: var(--epms-text-muted);">#</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">HIRARC No</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Rev No</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Rev Date</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Ops Center</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Process Loc</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Approved By</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stagingRows as $i => $row)
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <td class="px-3 py-3">{{ $i + 1 }}</td>
                            <td class="px-3 py-3">{{ $row->hirarc_no }}</td>
                            <td class="px-3 py-3">{{ $row->hirarc_rev_no }}</td>
                            <td class="px-3 py-3">{{ $row->hirarc_rev_date }}</td>
                            <td class="px-3 py-3">{{ $row->hirarc_ops_center_code }}</td>
                            <td class="px-3 py-3">{{ $row->hirarc_ploc_id }}</td>
                            <td class="px-3 py-3">{{ $row->hirarc_approved_by }}</td>
                            <td class="px-3 py-3">{{ $row->portal_status }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-xs" style="color: var(--epms-text-muted);">
                                No staged HIRARC templates. Click “Get Template From HSE” to fetch.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function hirarcTemplate() {
    return {
        loading: false,
        currentRows: @json($currentExists ? $currentRows : 0),
        newRows: @json($newRows),

        refreshInfo() {
            this.loading = true;
            fetch('{{ route('masters.hse.hirarc_template.refresh-info') }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(r => r.json())
            .then(res => {
                const d = res.data || res;
                this.currentRows = d.current_rows ?? this.currentRows;
                this.newRows = d.new_rows ?? this.newRows;
            })
            .catch(() => {})
            .finally(() => { this.loading = false; });
        },
    };
}
</script>
@endpush
