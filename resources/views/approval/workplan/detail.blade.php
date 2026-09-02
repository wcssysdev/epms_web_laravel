@extends('layouts.app')

@section('title', 'Workplan Approval Detail')

@section('breadcrumb')
    <li><span class="text-gray-500">Approval /</span></li>
    <li><a href="{{ route('approval.workplan.index') }}" class="text-gray-500 hover:text-primary">Workplan /</a></li>
    <li><span class="font-medium text-primary">{{ $divisionCode }}</span></li>
@endsection

@section('page-title', "Division {$divisionCode}")
@section('page-subtitle', \Carbon\Carbon::parse($date)->format('d M Y') . ' • by ' . $createdBy)

@section('page-actions')
    <a href="{{ route('approval.workplan.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
@php $isPending = $type === 'published'; @endphp

<form method="POST" action="{{ route('approval.workplan.submit') }}" x-data="approvalForm()">
    @csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <input type="hidden" name="division" value="{{ $divisionCode }}">
    <input type="hidden" name="created_by" value="{{ $createdBy }}">
    <input type="hidden" name="approval_type" x-model="decision">

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color: var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        @if($isPending)
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" @change="toggleAll($event.target.checked)" x-ref="all">
                        </th>
                        @endif
                        @foreach(['Activity','Block','Target','HK','Materials','Remark'] as $h)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($workplans as $idx => $wp)
                    <tr class="border-b align-top" style="border-color: var(--epms-border);">
                        @if($isPending)
                        <td class="px-4 py-3">
                            <input type="checkbox" name="workplan_ids[{{ $idx }}]" value="{{ $wp->id }}"
                                   x-ref="cb" @change="syncAll()" class="wp-checkbox">
                        </td>
                        @endif
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $wp->activity_code }}</span>
                            <span class="block text-xs" style="color: var(--epms-text-muted);">{{ $wp->activity_name }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $wp->block_code ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $wp->total_qty_target }}</td>
                        <td class="px-4 py-3">{{ $wp->total_hk }}</td>
                        <td class="px-4 py-3">
                            @if($wp->materials->isEmpty())
                                <span style="color: var(--epms-text-muted);">—</span>
                            @else
                                <ul class="space-y-0.5">
                                    @foreach($wp->materials as $m)
                                    <li class="text-xs">{{ $m->material_code }} <span style="color: var(--epms-text-muted);">×{{ $m->qty }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="px-4 py-3 min-w-[200px]">
                            @if($isPending)
                            <input type="text" name="remarks[{{ $idx }}]"
                                   placeholder="Remark (required if rejecting)"
                                   class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            @else
                                <span class="text-xs">{{ $wp->last_remark ?: '—' }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($isPending)
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t" style="border-color: var(--epms-border);">
            <p class="text-xs" style="color: var(--epms-text-muted);">
                Select workplans, then approve or reject. Rejection requires a remark on each selected row.
            </p>
            <div class="flex gap-3">
                <button type="submit" @click="decision = 'rejected'"
                        class="rounded-lg border border-red-300 bg-red-50 px-5 py-2.5 text-sm font-medium text-red-600 hover:bg-red-100 transition">
                    Reject Selected
                </button>
                <button type="submit" @click="decision = 'approved'"
                        class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 transition">
                    Approve Selected
                </button>
            </div>
        </div>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
function approvalForm() {
    return {
        decision: 'approved',
        checkboxes() { return Array.from(document.querySelectorAll('.wp-checkbox')); },
        toggleAll(checked) { this.checkboxes().forEach(cb => cb.checked = checked); },
        syncAll() {
            const all = this.checkboxes();
            const allChecked = all.length > 0 && all.every(cb => cb.checked);
            if (this.$refs.all) this.$refs.all.checked = allChecked;
        },
    }
}
</script>
@endpush
