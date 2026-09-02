@extends('layouts.app')

@section('title', 'GI Plan Detail')

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><a href="{{ route('transactions.gi_plan.index') }}" class="text-gray-500 hover:text-primary">GI Plan /</a></li>
    <li><span class="font-medium text-primary">Detail</span></li>
@endsection

@section('page-title', 'GI Plan Detail')
@section('page-subtitle', $giPlan->id)

@section('page-actions')
    <a href="{{ route('transactions.gi_plan.index') }}"
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

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">GI Header</h2>
            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $giPlan->statusBadgeClass() }}">{{ $giPlan->statusLabel() }}</span>
        </div>
        <dl class="p-5 grid gap-x-8 gap-y-4 md:grid-cols-2 text-sm" style="color: var(--epms-text);">
            @php
                $rows = [
                    'GI Number'     => $giPlan->id,
                    'Plan Date'     => $giPlan->plan_date?->format('d M Y'),
                    'Division'      => $giPlan->division_code ?: '—',
                    'Movement Type' => $giPlan->movement_type,
                    'SLoc'          => $giPlan->sloc_code ?: '—',
                    'Created By'    => $giPlan->created_by,
                ];
                if ($giPlan->isApproved()) $rows['Approved By'] = $giPlan->approved_by_name;
                if ($giPlan->approval_remark) $rows['Approval Remark'] = $giPlan->approval_remark;
            @endphp
            @foreach($rows as $label => $value)
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</dt>
                <dd class="mt-0.5 font-medium">{{ $value }}</dd>
            </div>
            @endforeach
        </dl>
    </div>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Material Lines</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color: var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        @foreach(['Code','Name','Qty','UOM','Cost Center','Order No.'] as $h)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color: var(--epms-text-muted);">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($details as $d)
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        <td class="px-4 py-3 font-medium">{{ $d->material_code }}</td>
                        <td class="px-4 py-3">{{ $d->material_name }}</td>
                        <td class="px-4 py-3">{{ $d->qty }}</td>
                        <td class="px-4 py-3">{{ $d->uom ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $d->cost_center ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $d->order_number ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm" style="color: var(--epms-text-muted);">No material lines.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
