@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><span class="font-medium text-primary">Planning / {{ $title }}</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'Manage GI (Goods Issue) plan')

@section('content')
<div x-data="giPlanPage('{{ $date }}')">

    {{-- Date filter --}}
    <form method="GET" class="flex items-end gap-3 mb-4 rounded-xl border px-5 py-4"
          style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">GI Date</label>
            <input type="date" name="date" value="{{ $date }}"
                   class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">Filter</button>
    </form>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-4 rounded-xl border overflow-hidden"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        @foreach([
            ['drafted', 'Draft', count($drafted)],
            ['submitted', 'Pending Approval', count($submitted)],
            ['approved', 'Approved', count($approved)],
            ['rejected', 'Rejected', count($rejected)],
        ] as [$tab, $label, $cnt])
        <button @click="activeTab='{{ $tab }}'"
                :class="activeTab==='{{ $tab }}' ? 'bg-primary text-white' : ''"
                class="flex-1 px-3 py-2.5 text-xs font-semibold transition hover:opacity-80">
            {{ $label }}
            <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                  :class="activeTab==='{{ $tab }}' ? 'bg-white text-primary' : 'bg-gray-100 text-gray-600'">
                {{ $cnt }}
            </span>
        </button>
        @endforeach
    </div>

    {{-- DRAFTED --}}
    <div x-show="activeTab==='drafted'" x-cloak>
        @include('planning.gi_plan._table', ['rows' => $drafted, 'tab' => 'drafted', 'canSubmit' => true])
    </div>

    {{-- SUBMITTED --}}
    <div x-show="activeTab==='submitted'" x-cloak>
        @include('planning.gi_plan._table', ['rows' => $submitted, 'tab' => 'submitted', 'canSubmit' => false])
    </div>

    {{-- APPROVED --}}
    <div x-show="activeTab==='approved'" x-cloak>
        @include('planning.gi_plan._table', ['rows' => $approved, 'tab' => 'approved', 'canSubmit' => false])
    </div>

    {{-- REJECTED --}}
    <div x-show="activeTab==='rejected'" x-cloak>
        @include('planning.gi_plan._table', ['rows' => $rejected, 'tab' => 'rejected', 'canSubmit' => false])
    </div>

</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none!important}</style>
@endpush
@push('scripts')
<script>
function giPlanPage(date) {
    return {
        activeTab: 'drafted',
        init() {
            const hash = window.location.hash.replace('#','');
            if (['drafted','submitted','approved','rejected'].includes(hash)) this.activeTab = hash;
        }
    }
}
</script>
@endpush
