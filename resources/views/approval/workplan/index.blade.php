@extends('layouts.app')

@section('title', 'Workplan Approval')

@section('breadcrumb')
    <li><span class="text-gray-500">Approval /</span></li>
    <li><span class="font-medium text-primary">Workplan</span></li>
@endsection

@section('page-title', 'Workplan Approval')
@section('page-subtitle', 'Review and approve published workplans')

@section('content')
<div x-data="{ tab: 'published' }">

    {{-- Date filter --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-5"
          style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Workplan Date</label>
            <input type="date" name="date" value="{{ $date->toDateString() }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">Show</button>
    </form>

    {{-- Tabs --}}
    <div class="flex gap-1 border-b mb-4" style="border-color: var(--epms-border);">
        @php
            $tabs = [
                'published' => ['Pending Approval', $published->count()],
                'approved'  => ['Approved',         $approved->count()],
                'rejected'  => ['Rejected',         $rejected->count()],
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

    @foreach(['published' => $published, 'approved' => $approved, 'rejected' => $rejected] as $key => $groups)
    <div x-show="tab === '{{ $key }}'" x-cloak>
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            @foreach(['#','Division','Date','Created By','Activities',''] as $h)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $i => $g)
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <td class="px-4 py-3">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium">{{ $g->division_code }}</td>
                            <td class="px-4 py-3">{{ \Carbon\Carbon::parse($g->workplan_date)->format('d M Y') }}</td>
                            <td class="px-4 py-3">{{ $g->created_by }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-primary/10 text-primary">{{ $g->activity_count }} activities</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('approval.workplan.detail', [
                                        'workplan_date' => $g->workplan_date,
                                        'division_code' => $g->division_code,
                                        'created_by'    => $g->created_by,
                                        'type'          => $key,
                                   ]) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:opacity-90 transition">
                                    {{ $key === 'published' ? 'Review' : 'View' }}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm" style="color: var(--epms-text-muted);">No {{ $key }} workplans for this date.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

</div>
@endsection

@push('styles')<style>[x-cloak]{display:none!important;}</style>@endpush
