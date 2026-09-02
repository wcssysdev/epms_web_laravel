@extends('layouts.app')

@section('title', 'Workplan Detail')

@section('breadcrumb')
    <li><span class="text-gray-500">Planning /</span></li>
    <li><a href="{{ route('planning.workplan.index') }}" class="text-gray-500 hover:text-primary">Workplan /</a></li>
    <li><span class="font-medium text-primary">Detail</span></li>
@endsection

@section('page-title', 'Workplan Detail')
@section('page-subtitle', $workplan->id)

@section('page-actions')
    <a href="{{ route('planning.workplan.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

    {{-- Summary --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Plan Information</h2>
            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium
                {{ $workplan->isApproved() ? 'bg-green-100 text-green-700' : '' }}
                {{ $workplan->isRejected() ? 'bg-red-100 text-red-700' : '' }}
                {{ $workplan->isPublished() ? 'bg-blue-100 text-blue-700' : '' }}
                {{ $workplan->isDraft() ? 'bg-gray-100 text-gray-600' : '' }}">
                {{ $workplan->statusLabel() }}
            </span>
        </div>
        <dl class="p-5 grid gap-x-8 gap-y-4 md:grid-cols-2 text-sm" style="color: var(--epms-text);">
            @php
                $rows = [
                    'Date'          => $workplan->workplan_date?->format('d M Y'),
                    'Division'      => $workplan->division_code,
                    'Activity'      => $workplan->activity_code . ' - ' . $workplan->activity_name,
                    'Block'         => $workplan->block_code ?: '—',
                    'Target Qty'    => $workplan->total_qty_target,
                    'Mandays (HK)'  => $workplan->total_hk,
                    'Order Number'  => $workplan->order_number ?: '—',
                    'AUC Number'    => $workplan->auc_number ?: '—',
                    'Cost Center'   => $workplan->cost_center ?: '—',
                    'Mandor'        => $workplan->mandor_employee_name ?: '—',
                    'Created By'    => $workplan->created_by,
                ];
                if ($workplan->isApproved()) $rows['Approved By'] = $workplan->approved_by_name;
                if ($workplan->approval_remark) $rows['Approval Remark'] = $workplan->approval_remark;
            @endphp
            @foreach($rows as $label => $value)
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</dt>
                <dd class="mt-0.5 font-medium">{{ $value }}</dd>
            </div>
            @endforeach
        </dl>
    </div>

    {{-- Materials --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Materials</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color: var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color: var(--epms-text-muted);">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color: var(--epms-text-muted);">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color: var(--epms-text-muted);">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($materials as $m)
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        <td class="px-4 py-3 font-medium">{{ $m->material_code }}</td>
                        <td class="px-4 py-3">{{ $m->material_name }}</td>
                        <td class="px-4 py-3">{{ $m->qty }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm" style="color: var(--epms-text-muted);">No materials.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
