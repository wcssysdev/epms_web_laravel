@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><span class="font-medium text-primary">Approval / {{ $title }}</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'Approve or reject unplanned coconut harvesting chit from mobile')

@section('content')
<div x-data="{ selected: [], showApprove: false, showReject: false }">
    <form method="GET" class="flex items-end gap-3 mb-4 rounded-xl border px-5 py-4"
          style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Date</label>
            <input type="date" name="date" value="{{ $date }}"
                   class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">Filter</button>
    </form>

    @if(count($pending) > 0)
    <div class="flex gap-3 mb-4">
        <button @click="selected.length > 0 && (showApprove = true)" :disabled="selected.length === 0"
                :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-700'"
                class="rounded-lg bg-green-600 text-white px-4 py-2 text-sm font-medium transition">
            ✓ Approve Selected (<span x-text="selected.length"></span>)
        </button>
        <button @click="selected.length > 0 && (showReject = true)" :disabled="selected.length === 0"
                :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-red-700'"
                class="rounded-lg bg-red-600 text-white px-4 py-2 text-sm font-medium transition">
            ✗ Reject Selected (<span x-text="selected.length"></span>)
        </button>
    </div>
    @endif

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="px-5 py-3 border-b" style="border-color:var(--epms-border);">
            <h3 class="font-semibold">Pending Approval ({{ count($pending) }})</h3>
        </div>

        @if(count($pending) === 0)
            <div class="p-8 text-center text-sm" style="color:var(--epms-text-muted);">No pending coconut harvesting chit for approval.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="color:var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color:var(--epms-border);">
                            <th class="px-3 py-3"><input type="checkbox" @click="selected = $event.target.checked ? {{ json_encode(array_column($pending, 'id')) }} : []" class="rounded"></th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Division</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Block</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Gang</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Checker</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Nuts Total</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">TPH</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $row)
                        @php $r = (array)$row; @endphp
                        <tr class="border-b hover:bg-opacity-50" style="border-color:var(--epms-border);">
                            <td class="px-3 py-2"><input type="checkbox" :value="{{ $r['id'] }}" x-model="selected" class="rounded"></td>
                            <td class="px-3 py-2">{{ $r['chit_date'] ?? '-' }}</td>
                            <td class="px-3 py-2 font-medium">{{ $r['division_code'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $r['block_code'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $r['gang_code'] ?? '-' }} - {{ $r['gang_name'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ $r['checker_employee_code'] ?? '-' }} - {{ $r['checker_employee_name'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ $r['nuts_total'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $r['tph_code'] ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div x-show="showApprove" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div @click.away="showApprove = false" class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Approve Coconut Harvesting Chit</h3>
            <form method="POST" action="{{ route('approval.harvesting_chit_coconut.approve') }}">
                @csrf
                <template x-for="id in selected"><input type="hidden" name="ids[]" :value="id"></template>
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

    <div x-show="showReject" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div @click.away="showReject = false" class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Reject Coconut Harvesting Chit</h3>
            <form method="POST" action="{{ route('approval.harvesting_chit_coconut.approve') }}">
                @csrf
                <template x-for="id in selected"><input type="hidden" name="ids[]" :value="id"></template>
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
