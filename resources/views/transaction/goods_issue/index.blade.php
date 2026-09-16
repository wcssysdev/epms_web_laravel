@extends('layouts.app')

@section('title', 'Goods Issue')

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><span class="font-medium text-primary">Goods Issue</span></li>
@endsection

@section('page-title', 'Goods Issue (GI)')
@section('page-subtitle', 'Manage material goods issue transactions')

@section('page-actions')
    <a href="{{ route('transactions.goods_issue.create') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add GI
    </a>
@endsection

@section('content')
<div x-data="{ showDelete: false, deleteUrl: '' }">

    {{-- Filter bar --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-5"
          style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">From</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">To</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">Show</button>
    </form>

    {{-- Table --}}
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <table class="w-full text-sm">
            <thead>
                <tr style="background: var(--epms-border);">
                    <th class="px-3 py-3 text-left font-semibold">#</th>
                    <th class="px-3 py-3 text-left font-semibold">GI ID</th>
                    <th class="px-3 py-3 text-left font-semibold">Date</th>
                    <th class="px-3 py-3 text-left font-semibold">Mvt Type</th>
                    <th class="px-3 py-3 text-left font-semibold">SLoc</th>
                    <th class="px-3 py-3 text-left font-semibold">Materials</th>
                    <th class="px-3 py-3 text-left font-semibold">SAP Doc</th>
                    <th class="px-3 py-3 text-left font-semibold">Status</th>
                    <th class="px-3 py-3 text-left font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y" style="divide-color: var(--epms-border);">
                @forelse($records as $i => $gi)
                <tr class="hover:bg-black/5 transition">
                    <td class="px-3 py-3" style="color: var(--epms-text-muted);">{{ $i + 1 }}</td>
                    <td class="px-3 py-3 font-mono text-xs font-medium" style="color: var(--epms-text);">{{ $gi->id }}</td>
                    <td class="px-3 py-3" style="color: var(--epms-text);">{{ $gi->gi_date->format('d M Y') }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded px-2 py-0.5 text-xs font-semibold" style="background: var(--epms-border); color: var(--epms-text);">{{ $gi->movement_type }}</span>
                    </td>
                    <td class="px-3 py-3 text-xs" style="color: var(--epms-text);">{{ $gi->sloc_code }}</td>
                    <td class="px-3 py-3 text-xs" style="color: var(--epms-text-muted);">{{ $gi->details->count() }} item(s)</td>
                    <td class="px-3 py-3 font-mono text-xs" style="color: var(--epms-text);">{{ $gi->gi_document_number ?: '-' }}</td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $gi->statusBadgeClass() }}">
                            {{ $gi->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex gap-1">
                            <a href="{{ route('transactions.goods_issue.detail', $gi->id) }}" class="btn-action btn-view" title="View">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            @if($gi->isEditable())
                            <a href="{{ route('transactions.goods_issue.edit', $gi->id) }}" class="btn-action btn-edit" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <button @click="deleteUrl = '{{ route('transactions.goods_issue.destroy', $gi->id) }}'; showDelete = true"
                                    class="btn-action btn-danger" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-3 py-10 text-center text-sm" style="color: var(--epms-text-muted);">No Goods Issue records for selected date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Delete modal --}}
    <div x-show="showDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showDelete = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-base font-bold mb-2" style="color: var(--epms-text);">Confirm Delete</h3>
            <p class="text-sm mb-5" style="color: var(--epms-text-muted);">Delete this Goods Issue record? This cannot be undone.</p>
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
    .btn-view  { background:#f0fdf4; border-color:#bbf7d0; color:#16a34a; }
    .btn-edit  { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
    .btn-danger{ background:#fef2f2; border-color:#fecaca; color:#dc2626; }
</style>
@endpush
