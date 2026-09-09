@extends('layouts.app')
@section('title', $title . ' — SAP Closing Approval')
@section('breadcrumb')
    <li><span class="font-medium text-primary">SAP Approval / {{ $title }}</span></li>
@endsection
@section('page-title', $title . ' — SAP Closing Approval')
@section('page-subtitle', 'Approve/reject locked records before SAP submission')

@section('content')
<div x-data="approvalPage('{{ $routePrefix }}','{{ $date }}')">

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
            ['pending','Pending Approval','yellow'],
            ['approved','Approved','green'],
            ['rejected','Rejected','red'],
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

    {{-- PENDING --}}
    <div x-show="activeTab==='pending'" x-cloak>
        @include('sap-approval._shared._table', [
            'rows'       => $pending,
            'tab'        => 'pending',
            'actions'    => ['approve','reject'],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
        ])
    </div>

    {{-- APPROVED --}}
    <div x-show="activeTab==='approved'" x-cloak>
        @include('sap-approval._shared._table', [
            'rows'       => $approved,
            'tab'        => 'approved',
            'actions'    => [],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
        ])
    </div>

    {{-- REJECTED --}}
    <div x-show="activeTab==='rejected'" x-cloak>
        @include('sap-approval._shared._table', [
            'rows'       => $rejected,
            'tab'        => 'rejected',
            'actions'    => [],
            'routePrefix'=> $routePrefix,
            'date'       => $date,
        ])
    </div>

</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none!important}</style>
@endpush
@push('scripts')
<script>
function approvalPage(prefix, date) {
    return {
        activeTab: 'pending',
        init() {
            const hash = window.location.hash.replace('#','');
            if (['pending','approved','rejected'].includes(hash)) this.activeTab = hash;
        }
    }
}
</script>
@endpush
