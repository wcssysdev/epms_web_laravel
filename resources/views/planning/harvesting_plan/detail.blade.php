@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><a href="{{ route('planning.harvesting_plan.index', ['date' => $date]) }}" class="hover:text-primary">Planning / Harvesting Plan (Palm)</a></li>
    <li><span class="font-medium text-primary">Detail</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'Harvesting plan for ' . $division . ' on ' . $date)

@section('content')
<div class="rounded-xl border shadow-sm overflow-hidden mb-4"
     style="background:var(--epms-header-bg);border-color:var(--epms-border);">
    <div class="px-5 py-3 border-b flex items-center justify-between" style="border-color:var(--epms-border);">
        <h3 class="font-semibold">Harvesting Plan Blocks ({{ count($plans) }} total)</h3>
        <a href="{{ route('planning.harvesting_plan.index', ['date' => $date]) }}"
           class="text-sm text-primary hover:underline">← Back to List</a>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm" style="color:var(--epms-text);">
            <thead>
                <tr class="border-b" style="border-color:var(--epms-border);">
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Block</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Assistant Manager</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Qty Target (Kg)</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">HA</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Total HK</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($plans as $plan)
                @php $p = (array)$plan; @endphp
                <tr class="border-b hover:bg-opacity-50" style="border-color:var(--epms-border);">
                    <td class="px-3 py-2 font-medium">{{ $p['block_code'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $p['assistant_emp_code'] ?? '-' }} - {{ $p['assistant_emp_name'] ?? '' }}</td>
                    <td class="px-3 py-2">{{ number_format($p['qty_target'] ?? 0, 0) }}</td>
                    <td class="px-3 py-2">{{ number_format($p['ha'] ?? 0, 2) }}</td>
                    <td class="px-3 py-2">{{ $p['total_hk'] ?? '-' }}</td>
                    <td class="px-3 py-2">
                        @php
                            $status = $p['is_approved'] ?? null;
                            [$badge, $color] = match($status) {
                                null => ['Draft', 'bg-gray-100 text-gray-600'],
                                0 => ['Pending', 'bg-yellow-100 text-yellow-700'],
                                1 => ['Approved', 'bg-green-100 text-green-700'],
                                -1 => ['Rejected', 'bg-red-100 text-red-700'],
                                default => ['Unknown', 'bg-gray-100 text-gray-500']
                            };
                        @endphp
                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $color }}">{{ $badge }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
