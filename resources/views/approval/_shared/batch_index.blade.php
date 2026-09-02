{{--
  Shared batch-approval index. Expected variables:
    $title        page title
    $routePrefix  e.g. 'approval.overtime'
    $idField      POST array field name for ids, e.g. 'overtime_ids'
    $date         Carbon date
    $pending, $approved, $rejected  collections
    $noAccess     bool
    $columns      array of ['label' => closure($row) => string]  for display
    $hasDetail    bool — whether rows link to a detail page (route {prefix}.detail)
    $menu         sidebar menu key (for sub_menu highlight) — optional
--}}
@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><span class="text-gray-500">Approval /</span></li>
    <li><span class="font-medium text-primary">{{ $title }}</span></li>
@endsection

@section('page-title', $title)
@section('page-subtitle', 'Review and approve submitted records')

@section('content')
<div x-data="batchApproval()">

    {{-- Date filter --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-5"
          style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Date</label>
            <input type="date" name="date" value="{{ $date->toDateString() }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">Show</button>
    </form>

    @if($noAccess)
        <div class="rounded-xl border px-5 py-10 text-center text-sm"
             style="background: var(--epms-header-bg); border-color: var(--epms-border); color: var(--epms-text-muted);">
            You have no divisions assigned. Contact your Estate Manager to be mapped to a division.
        </div>
    @else

    {{-- Tabs --}}
    <div class="flex gap-1 border-b mb-4" style="border-color: var(--epms-border);">
        @php
            $tabs = [
                'pending'  => ['Pending Approval', $pending->count()],
                'approved' => ['Approved',         $approved->count()],
                'rejected' => ['Rejected',         $rejected->count()],
            ];
        @endphp
        @foreach($tabs as $key => [$label, $count])
        <button @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent'"
                class="flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                style="color: var(--epms-text);">
            {{ $label }}
            <span class="rounded-full px-2 py-0.5 text-xs" style="background: var(--epms-border); color: var(--epms-text-muted);">{{ $count }}</span>
        </button>
        @endforeach
    </div>

    @foreach(['pending' => $pending, 'approved' => $approved, 'rejected' => $rejected] as $key => $items)
    <div x-show="tab === '{{ $key }}'" x-cloak>
        @php $isPending = $key === 'pending'; @endphp
        <form method="POST" action="{{ route($routePrefix.'.submit') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="approval_type" x-model="decision">

            <div class="rounded-xl border shadow-sm overflow-hidden"
                 style="background: var(--epms-header-bg); border-color: var(--epms-border);">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="color: var(--epms-text);">
                        <thead>
                            <tr class="border-b" style="border-color: var(--epms-border);">
                                @if($isPending)
                                <th class="px-4 py-3 w-10"><input type="checkbox" @change="toggleAll($event.target.checked)" x-ref="all"></th>
                                @endif
                                @foreach($columns as $label => $fn)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Status</th>
                                @if($hasDetail)
                                <th class="px-4 py-3"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $row)
                            <tr class="border-b" style="border-color: var(--epms-border);">
                                @if($isPending)
                                <td class="px-4 py-3"><input type="checkbox" name="{{ $idField }}[]" value="{{ $row->id }}" class="batch-cb" @change="syncAll()"></td>
                                @endif
                                @foreach($columns as $label => $fn)
                                <td class="px-4 py-3">{{ $fn($row) }}</td>
                                @endforeach
                                <td class="px-4 py-3">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $row->isApproved() ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $row->isRejected() ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $row->isPending() ? 'bg-gray-100 text-gray-600' : '' }}">
                                        {{ $row->statusLabel() }}
                                    </span>
                                </td>
                                @if($hasDetail)
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route($routePrefix.'.detail', $row->id) }}"
                                       class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition hover:opacity-80"
                                       style="border-color: var(--epms-border); color: var(--epms-text);">View</a>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr><td colspan="{{ count($columns) + ($isPending?2:1) + ($hasDetail?1:0) }}" class="px-4 py-10 text-center text-sm" style="color: var(--epms-text-muted);">No {{ $key }} records for this date.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($isPending && $items->isNotEmpty())
                <div class="flex items-center justify-end gap-3 px-5 py-4 border-t" style="border-color: var(--epms-border);">
                    <button type="submit" @click="decision = 'rejected'"
                            class="rounded-lg border border-red-300 bg-red-50 px-5 py-2.5 text-sm font-medium text-red-600 hover:bg-red-100 transition">
                        Reject Selected
                    </button>
                    <button type="submit" @click="decision = 'approved'"
                            class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 transition">
                        Approve Selected
                    </button>
                </div>
                @endif
            </div>
        </form>
    </div>
    @endforeach

    @endif
</div>
@endsection

@push('styles')<style>[x-cloak]{display:none!important;}</style>@endpush

@push('scripts')
<script>
function batchApproval() {
    return {
        tab: 'pending',
        decision: 'approved',
        cbs() { return Array.from(document.querySelectorAll('.batch-cb')); },
        toggleAll(checked) { this.cbs().forEach(cb => cb.checked = checked); },
        syncAll() {
            if (this.$refs.all) {
                const all = this.cbs();
                this.$refs.all.checked = all.length > 0 && all.every(cb => cb.checked);
            }
        },
    }
}
</script>
@endpush
