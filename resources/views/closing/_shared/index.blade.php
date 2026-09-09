@extends('layouts.app')
@section('title', $title . ' — Closing SAP')
@section('breadcrumb')
    <li><span class="font-medium text-primary">Closing / {{ $title }}</span></li>
@endsection
@section('page-title', $title . ' — Closing SAP')
@section('page-subtitle', 'Manage SAP submission for ' . strtolower($title) . ' records')

@section('content')
<div x-data="closingPage('{{ $routePrefix }}','{{ $date }}')">

    {{-- Date filter --}}
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

    {{-- Tab navigation --}}
    <div class="flex gap-1 mb-4 rounded-xl border overflow-hidden"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        @foreach([
            ['opened','Unclosed','yellow'],
            ['locked','Locked','blue'],
            ['success','Success','green'],
            ['failed','Failed','red'],
            ['adjustment','Adjustment','purple'],
        ] as [$tab,$label,$color])
        <button @click="activeTab='{{ $tab }}'"
                :class="activeTab==='{{ $tab }}' ? 'bg-primary text-white' : ''"
                class="flex-1 px-3 py-2.5 text-xs font-semibold transition hover:opacity-80">
            {{ $label }}
            <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                  :class="activeTab==='{{ $tab }}' ? 'bg-white text-primary' : 'bg-gray-100 text-gray-600'">
                {{ count($$tab) }}
            </span>
        </button>
        @endforeach
    </div>

    {{-- OPENED / UNCLOSED --}}
    <div x-show="activeTab==='opened'" x-cloak>
        @include('closing._shared._table', [
            'rows'       => $opened,
            'tab'        => 'opened',
            'actions'    => ['lock','close'],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
            'columns'    => $columns ?? [],
        ])
    </div>

    {{-- LOCKED --}}
    <div x-show="activeTab==='locked'" x-cloak>
        @include('closing._shared._table', [
            'rows'       => $locked,
            'tab'        => 'locked',
            'actions'    => ['close'],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
            'columns'    => $columns ?? [],
        ])
    </div>

    {{-- SUCCESS --}}
    <div x-show="activeTab==='success'" x-cloak>
        @include('closing._shared._table', [
            'rows'       => $success,
            'tab'        => 'success',
            'actions'    => [],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
            'columns'    => $columns ?? [],
        ])
    </div>

    {{-- FAILED --}}
    <div x-show="activeTab==='failed'" x-cloak>
        @include('closing._shared._table', [
            'rows'       => $failed,
            'tab'        => 'failed',
            'actions'    => ['close'],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
            'columns'    => $columns ?? [],
        ])
    </div>

    {{-- ADJUSTMENT --}}
    <div x-show="activeTab==='adjustment'" x-cloak>
        @include('closing._shared._table', [
            'rows'       => $adjustment,
            'tab'        => 'adjustment',
            'actions'    => ['relock'],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
            'columns'    => $columns ?? [],
        ])
    </div>

</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none!important}</style>
@endpush
@push('scripts')
<script>
function closingPage(prefix, date) {
    return {
        activeTab: 'opened',
        selectedIds: [],
        init() {
            const hash = window.location.hash.replace('#','');
            if (['opened','locked','success','failed','adjustment'].includes(hash)) this.activeTab = hash;
        }
    }
}
</script>
@endpush
