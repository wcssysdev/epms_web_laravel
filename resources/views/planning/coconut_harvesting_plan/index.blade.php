@extends('layouts.app')

@section('title', 'Coconut Harvesting Plan')

@section('breadcrumb')
    <li><span class="text-gray-500">Planning /</span></li>
    <li><span class="font-medium text-primary">Coconut Harvesting Plan</span></li>
@endsection

@section('page-title', 'Coconut Harvesting Plan')
@section('page-subtitle', 'Plan coconut harvesting targets per block')

@section('page-actions')
    <a href="{{ route('planning.coconut_harvesting_plan.create') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Plan
    </a>
@endsection

@section('content')
<div x-data="coconutPlanPage()">

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-5"
          style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Plan Date</label>
            <input type="date" name="date" value="{{ $date->toDateString() }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">Show</button>
    </form>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color: var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        @foreach(['#','Division','Block','HA','Target','HK','Assistant','Status','Actions'] as $h)
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $i => $p)
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        <td class="px-3 py-3">{{ $i + 1 }}</td>
                        <td class="px-3 py-3 font-medium">{{ $p->division_code }}</td>
                        <td class="px-3 py-3">{{ $p->block_code }}</td>
                        <td class="px-3 py-3">{{ $p->ha ?: '—' }}</td>
                        <td class="px-3 py-3">{{ number_format($p->qty_target) }}</td>
                        <td class="px-3 py-3">{{ $p->total_hk }}</td>
                        <td class="px-3 py-3">{{ $p->assistant_emp_name ?: '—' }}</td>
                        <td class="px-3 py-3">
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium
                                {{ $p->isApproved() ? 'bg-green-100 text-green-700' : '' }}
                                {{ $p->isRejected() ? 'bg-red-100 text-red-700' : '' }}
                                {{ $p->isPublished() ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $p->isDraft() ? 'bg-gray-100 text-gray-600' : '' }}">
                                {{ $p->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex gap-1">
                                @if($canApprove && $p->isPublished())
                                <button @click="openApprove({{ $p->id }})" class="btn-action btn-approve" title="Approve / Reject">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                                @endif
                                @if($p->isEditable())
                                <a href="{{ route('planning.coconut_harvesting_plan.edit', $p->id) }}" class="btn-action btn-edit" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <button @click="deleteId = {{ $p->id }}; showDelete = true" class="btn-action btn-danger" title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-3 py-10 text-center text-sm" style="color: var(--epms-text-muted);">No coconut harvesting plans for this date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Approve / Reject modal --}}
    <div x-show="showApprove" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showApprove = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-base font-bold mb-3" style="color: var(--epms-text);">Approve / Reject Plan</h3>
            <form :action="approveUrl" method="POST">
                @csrf
                <label class="block text-sm mb-1" style="color: var(--epms-text);">Remark</label>
                <textarea name="remark" rows="3"
                          class="w-full rounded-lg border px-3 py-2 text-sm mb-4 outline-none focus:border-primary"
                          style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);"
                          placeholder="Required when rejecting"></textarea>
                <div class="flex gap-3 justify-end">
                    <button type="submit" name="decision" value="rejected"
                            class="px-4 py-2 rounded-lg text-sm font-medium border border-red-300 bg-red-50 text-red-600 hover:bg-red-100 transition">Reject</button>
                    <button type="submit" name="decision" value="approved"
                            class="px-4 py-2 rounded-lg text-sm font-medium bg-green-600 text-white hover:bg-green-700 transition">Approve</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete modal --}}
    <div x-show="showDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showDelete = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-base font-bold mb-2" style="color: var(--epms-text);">Confirm Delete</h3>
            <p class="text-sm mb-5" style="color: var(--epms-text-muted);">Delete this coconut harvesting plan?</p>
            <div class="flex gap-3 justify-end">
                <button @click="showDelete = false" class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</button>
                <form :action="`{{ url('planning/coconut-harvesting-plan') }}/${deleteId}`" method="POST" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition">Delete</button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
    [x-cloak]{display:none!important;}
    .btn-action { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; border:1px solid; transition:opacity 0.15s; cursor:pointer; }
    .btn-action:hover { opacity: 0.75; }
    .btn-approve { background:#f0fdf4; border-color:#bbf7d0; color:#16a34a; }
    .btn-edit { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
    .btn-danger { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
</style>
@endpush

@push('scripts')
<script>
function coconutPlanPage() {
    return {
        showApprove: false, approveUrl: '',
        showDelete: false, deleteId: null,
        openApprove(id) {
            this.approveUrl = `{{ url('planning/coconut-harvesting-plan') }}/${id}/approve`;
            this.showApprove = true;
        },
    }
}
</script>
@endpush
