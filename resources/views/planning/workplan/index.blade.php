@extends('layouts.app')

@section('title', 'Workplan')

@section('breadcrumb')
    <li><span class="text-gray-500">Planning /</span></li>
    <li><span class="font-medium text-primary">Workplan</span></li>
@endsection

@section('page-title', 'Workplan')
@section('page-subtitle', 'Plan daily field activities for approval')

@section('page-actions')
    <a href="{{ route('planning.workplan.create') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Workplan
    </a>
@endsection

@section('content')
<div x-data="{ tab: 'drafts', showDelete: false, deleteUrl: '' }">

    {{-- Date filter --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border px-5 py-4 mb-5"
          style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Workplan Date</label>
            <input type="date" name="date" value="{{ $date->toDateString() }}"
                   class="rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
        </div>
        <button type="submit"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">
            Show
        </button>
    </form>

    {{-- Status tabs --}}
    <div class="flex gap-1 border-b mb-4" style="border-color: var(--epms-border);">
        @php
            $tabs = [
                'drafts'    => ['Draft',     $drafts->count(),    'badge-ghost'],
                'published' => ['Published', $published->count(), 'badge-info'],
                'approved'  => ['Approved',  $approved->count(),  'badge-success'],
                'rejected'  => ['Rejected',  $rejected->count(),  'badge-error'],
            ];
        @endphp
        @foreach($tabs as $key => [$label, $count, $badge])
        <button @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent'"
                class="flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                style="color: var(--epms-text);">
            {{ $label }}
            <span class="rounded-full px-2 py-0.5 text-xs"
                  style="background: var(--epms-border); color: var(--epms-text-muted);">{{ $count }}</span>
        </button>
        @endforeach
    </div>

    {{-- Tab panels --}}
    @foreach(['drafts' => $drafts, 'published' => $published, 'approved' => $approved, 'rejected' => $rejected] as $key => $items)
    <div x-show="tab === '{{ $key }}'" x-cloak>
        @include('planning.workplan._table', ['items' => $items, 'status' => $key])
    </div>
    @endforeach

    {{-- Delete confirm modal --}}
    <div x-show="showDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showDelete = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-base font-bold mb-2" style="color: var(--epms-text);">Confirm Delete</h3>
            <p class="text-sm mb-5" style="color: var(--epms-text-muted);">Delete this workplan? This cannot be undone.</p>
            <div class="flex gap-3 justify-end">
                <button @click="showDelete = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</button>
                <form :action="deleteUrl" method="POST" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition">Delete</button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
