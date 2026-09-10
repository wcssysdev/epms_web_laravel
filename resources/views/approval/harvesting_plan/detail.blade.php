@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><a href="{{ route('approval.harvesting_plan.index', ['type' => $type, 'date' => $date]) }}" class="hover:text-primary">Approval / Harvesting Plan ({{ ucfirst($type) }})</a></li>
    <li><span class="font-medium text-primary">Review</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'Review and approve harvesting plan for ' . $division . ' on ' . $date)

@section('content')
<div x-data="{ showApprove: false, showReject: false }">

    {{-- Plan Detail Table --}}
    <div class="rounded-xl border shadow-sm overflow-hidden mb-4"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="px-5 py-3 border-b flex items-center justify-between" style="border-color:var(--epms-border);">
            <h3 class="font-semibold">Harvesting Plan Blocks ({{ count($plans) }} total)</h3>
            <a href="{{ route('approval.harvesting_plan.index', ['type' => $type, 'date' => $date]) }}"
               class="text-sm text-primary hover:underline">← Back to List</a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color:var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color:var(--epms-border);">
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Block</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Assistant Manager</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Qty Target</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">HA</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Total HK</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plans as $plan)
                    @php $p = (array)$plan; @endphp
                    <tr class="border-b hover:bg-opacity-50" style="border-color:var(--epms-border);">
                        <td class="px-3 py-2 font-medium">{{ $p['block_code'] ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $p['assistant_emp_code'] ?? '-' }} - {{ $p['assistant_emp_name'] ?? '' }}</td>
                        <td class="px-3 py-2">{{ number_format($p['qty_target'] ?? 0, 0) }} {{ $type === 'coconut' ? 'pcs' : 'kg' }}</td>
                        <td class="px-3 py-2">{{ number_format($p['ha'] ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ $p['total_hk'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="flex gap-3">
        <button @click="showApprove = true" class="rounded-lg bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 text-sm font-medium transition">
            ✓ Approve
        </button>
        <button @click="showReject = true" class="rounded-lg bg-red-600 hover:bg-red-700 text-white px-6 py-2.5 text-sm font-medium transition">
            ✗ Reject
        </button>
    </div>

    {{-- Approve Modal --}}
    <div x-show="showApprove" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div @click.away="showApprove = false" class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Approve Harvesting Plan</h3>
            <form method="POST" action="{{ route('approval.harvesting_plan.approve') }}">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="division" value="{{ $division }}">
                <input type="hidden" name="created_by" value="{{ $createdBy }}">
                <input type="hidden" name="action" value="approve">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Remark (optional)</label>
                    <textarea name="remark" rows="3" class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="showApprove = false" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700">Confirm Approve</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div x-show="showReject" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div @click.away="showReject = false" class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Reject Harvesting Plan</h3>
            <form method="POST" action="{{ route('approval.harvesting_plan.approve') }}">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="division" value="{{ $division }}">
                <input type="hidden" name="created_by" value="{{ $createdBy }}">
                <input type="hidden" name="action" value="reject">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Remark (required)</label>
                    <textarea name="remark" rows="3" required class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="showReject = false" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none!important}</style>
@endpush
