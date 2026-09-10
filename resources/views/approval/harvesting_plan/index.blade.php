@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><span class="font-medium text-primary">Approval / {{ $title }}</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'Approve or reject harvesting plan submissions')

@section('content')
<div>
    {{-- Type + Date filter --}}
    <form method="GET" class="flex items-end gap-3 mb-4 rounded-xl border px-5 py-4"
          style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Type</label>
            <select name="type" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                    style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
                <option value="palm" {{ $type === 'palm' ? 'selected' : '' }}>Palm</option>
                <option value="coconut" {{ $type === 'coconut' ? 'selected' : '' }}>Coconut</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Plan Date</label>
            <input type="date" name="date" value="{{ $date }}"
                   class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">Filter</button>
    </form>

    {{-- Pending List --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="px-5 py-3 border-b" style="border-color:var(--epms-border);">
            <h3 class="font-semibold">Pending Approval ({{ count($pending) }})</h3>
        </div>

        @if(count($pending) === 0)
            <div class="p-8 text-center text-sm" style="color:var(--epms-text-muted);">
                No pending harvesting plans for approval.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="color:var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color:var(--epms-border);">
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color:var(--epms-text-muted);">Division</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color:var(--epms-text-muted);">Assistant Manager</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color:var(--epms-text-muted);">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color:var(--epms-text-muted);">Blocks</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color:var(--epms-text-muted);">Total Target</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color:var(--epms-text-muted);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $row)
                        @php $r = (array)$row; @endphp
                        <tr class="border-b hover:opacity-80" style="border-color:var(--epms-border);">
                            <td class="px-3 py-2 font-medium">{{ $r['division_code'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $r['assistant_emp_code'] ?? '-' }} - {{ $r['assistant_emp_name'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $r['plan_date'] ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-yellow-100 text-yellow-700 px-2 py-0.5 text-xs font-semibold">
                                    {{ $r['block_count'] ?? 0 }} blocks
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ number_format($r['total_qty'] ?? 0, 0) }} {{ $type === 'coconut' ? 'pcs' : 'kg' }}</td>
                            <td class="px-3 py-2">
                                <a href="{{ route('approval.harvesting_plan.detail', [
                                        'type' => $type,
                                        'date' => $r['plan_date'] ?? $date,
                                        'division' => $r['division_code'] ?? '',
                                        'created_by' => $r['created_by'] ?? ''
                                    ]) }}"
                                   class="text-primary hover:underline text-xs font-medium">
                                    Review & Approve
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
