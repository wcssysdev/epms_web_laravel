@extends('layouts.app')

@section('title', 'GI Plan')

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><span class="font-medium text-primary">GI Plan</span></li>
@endsection

@section('page-title', 'Goods Issue Plan')
@section('page-subtitle', 'Plan material goods issues for approval')

@section('page-actions')
    <a href="{{ route('transactions.gi_plan.create') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add GI Plan
    </a>
@endsection

@section('content')
<div x-data="{ tab: 'drafts', showDelete: false, deleteUrl: '', showApprove: false, approveUrl: '' }">

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

    <div class="flex gap-1 border-b mb-4" style="border-color: var(--epms-border);">
        @php
            $tabs = [
                'drafts'    => ['Draft',     $drafts->count()],
                'published' => ['Published', $published->count()],
                'approved'  => ['Approved',  $approved->count()],
                'rejected'  => ['Rejected',  $rejected->count()],
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

    @foreach(['drafts' => $drafts, 'published' => $published, 'approved' => $approved, 'rejected' => $rejected] as $key => $items)
    <div x-show="tab === '{{ $key }}'" x-cloak>
        <div class="rounded-xl border shadow-sm overflow-hidden"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            @foreach(['#','GI Number','Division','Movement','SLoc','Lines','Status','Actions'] as $h)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $i => $gi)
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <td class="px-3 py-3">{{ $i + 1 }}</td>
                            <td class="px-3 py-3 font-medium">{{ $gi->id }}</td>
                            <td class="px-3 py-3">{{ $gi->division_code ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $gi->movement_type }}</td>
                            <td class="px-3 py-3">{{ $gi->sloc_code ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $gi->details()->count() }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $gi->statusBadgeClass() }}">
                                    {{ $gi->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex gap-1">
                                    <a href="{{ route('transactions.gi_plan.show', $gi->id) }}" class="btn-action btn-view" title="View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    @if($canApprove && $gi->isPublished())
                                    <button @click="approveUrl = '{{ url('transactions/gi-plan') }}/{{ $gi->id }}/approve'; showApprove = true" class="btn-action btn-approve" title="Approve / Reject">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                    @endif
                                    @if($gi->isEditable())
                                    <a href="{{ route('transactions.gi_plan.edit', $gi->id) }}" class="btn-action btn-edit" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @if($gi->isDraft())
                                    <form action="{{ route('transactions.gi_plan.publish', $gi->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn-action btn-publish" title="Publish for approval">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                            </svg>
                                        </button>
                                    </form>
                                    @endif
                                    <button @click="deleteUrl = '{{ route('transactions.gi_plan.destroy', $gi->id) }}'; showDelete = true" class="btn-action btn-danger" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="px-3 py-10 text-center text-sm" style="color: var(--epms-text-muted);">No {{ $key }} GI plans for this date.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

    {{-- Approve modal --}}
    <div x-show="showApprove" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showApprove = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-base font-bold mb-3" style="color: var(--epms-text);">Approve / Reject GI Plan</h3>
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
            <p class="text-sm mb-5" style="color: var(--epms-text-muted);">Delete this GI plan? This cannot be undone.</p>
            <div class="flex gap-3 justify-end">
                <button @click="showDelete = false" class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</button>
                <form :action="deleteUrl" method="POST" class="inline">
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
    .btn-view { background:#f0fdf4; border-color:#bbf7d0; color:#16a34a; }
    .btn-approve { background:#ecfdf5; border-color:#a7f3d0; color:#059669; }
    .btn-edit { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
    .btn-publish { background:#fefce8; border-color:#fef08a; color:#ca8a04; }
    .btn-danger { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
</style>
@endpush
