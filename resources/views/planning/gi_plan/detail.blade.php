@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><a href="{{ route('planning.gi_plan.index', ['date' => $header->gi_date ?? today()->addDay()->toDateString()]) }}" class="hover:text-primary">Planning / GI Plan</a></li>
    <li><span class="font-medium text-primary">Detail</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'GI Plan for ' . ($header->estate_code ?? '-') . ' on ' . ($header->gi_date ?? '-'))

@section('content')
@php $h = (array)$header; @endphp

{{-- Header Info --}}
<div class="rounded-xl border shadow-sm mb-4 px-5 py-4"
     style="background:var(--epms-header-bg);border-color:var(--epms-border);">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
            <span class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">GI Date</span>
            <span class="font-semibold">{{ $h['gi_date'] ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Estate</span>
            <span class="font-semibold">{{ $h['estate_code'] ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Plant</span>
            <span class="font-semibold">{{ $h['plant_code'] ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Doc Number</span>
            <span class="font-semibold">{{ $h['gi_document_number'] ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Status</span>
            @php
                $status = $h['is_approved'] ?? null;
                [$badge, $color] = match($status) {
                    null => ['Draft', 'bg-gray-100 text-gray-600'],
                    0 => ['Pending', 'bg-yellow-100 text-yellow-700'],
                    1 => ['Approved', 'bg-green-100 text-green-700'],
                    -1 => ['Rejected', 'bg-red-100 text-red-700'],
                    default => ['Unknown', 'bg-gray-100 text-gray-500']
                };
            @endphp
            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $color }}">{{ $badge }}</span>
        </div>
        <div>
            <span class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Created By</span>
            <span class="font-semibold">{{ $h['created_by'] ?? '-' }}</span>
        </div>
    </div>
</div>

{{-- Detail Items --}}
<div class="rounded-xl border shadow-sm overflow-hidden"
     style="background:var(--epms-header-bg);border-color:var(--epms-border);">
    <div class="px-5 py-3 border-b flex items-center justify-between" style="border-color:var(--epms-border);">
        <h3 class="font-semibold">Material Items ({{ count($details) }} total)</h3>
        <a href="{{ route('planning.gi_plan.index', ['date' => $h['gi_date'] ?? today()->addDay()->toDateString()]) }}"
           class="text-sm text-primary hover:underline">← Back to List</a>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm" style="color:var(--epms-text);">
            <thead>
                <tr class="border-b" style="border-color:var(--epms-border);">
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Material</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Material Name</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Qty</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">UOM</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Cost Center</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">WBS</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Order No</th>
                </tr>
            </thead>
            <tbody>
                @foreach($details as $detail)
                @php $d = (array)$detail; @endphp
                <tr class="border-b hover:bg-opacity-50" style="border-color:var(--epms-border);">
                    <td class="px-3 py-2 font-medium">{{ $d['material_code'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $d['material_name'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ number_format($d['qty'] ?? 0, 2) }}</td>
                    <td class="px-3 py-2">{{ $d['uom'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $d['cost_center'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $d['wbs_code'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $d['order_number'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
