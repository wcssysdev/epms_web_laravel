@extends('layouts.app')

@section('title', ($giPlan ? 'Edit' : 'Add') . ' GI Plan')

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><a href="{{ route('transactions.gi_plan.index') }}" class="text-gray-500 hover:text-primary">GI Plan /</a></li>
    <li><span class="font-medium text-primary">{{ $giPlan ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($giPlan ? 'Edit' : 'Add') . ' GI Plan')

@section('page-actions')
    <a href="{{ route('transactions.gi_plan.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
<form method="POST"
      action="{{ $giPlan ? route('transactions.gi_plan.update', $giPlan->id) : route('transactions.gi_plan.store') }}"
      x-data="giPlanForm()"
      class="max-w-4xl">
    @csrf
    @if($giPlan) @method('PUT') @endif

    {{-- Header --}}
    <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">GI Header</h2>
        </div>
        <div class="p-5 grid gap-x-5 md:grid-cols-2">
            <x-form.input label="Plan Date" name="plan_date" type="date" :required="true"
                          :value="$giPlan?->plan_date?->toDateString() ?? $date" />

            <x-form.select label="Division" name="division_code">
                <option value="">— Optional —</option>
                @foreach($divisions as $d)
                    <option value="{{ $d->division_code }}" @selected(old('division_code', $giPlan?->division_code) === $d->division_code)>
                        {{ $d->division_code }} - {{ $d->division_name }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.select label="Movement Type" name="movement_type" :required="true">
                <option value="">— Select —</option>
                @foreach($movementTypes as $mt)
                    <option value="{{ $mt->mvt_type_code }}" @selected(old('movement_type', $giPlan?->movement_type) === $mt->mvt_type_code)>
                        {{ $mt->mvt_type_code }} - {{ $mt->mvt_type_desc }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.input label="Storage Location (SLoc)" name="sloc_code"
                          :value="$giPlan?->sloc_code" placeholder="Optional" />
        </div>
    </div>

    {{-- Material lines --}}
    <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Material Lines</h2>
            <button type="button" @click="addLine()"
                    class="flex items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary/20 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Line
            </button>
        </div>
        <div class="p-5">
            <template x-if="lines.length === 0">
                <p class="text-sm text-center py-4" style="color: var(--epms-text-muted);">No material lines added.</p>
            </template>
            <template x-for="(l, idx) in lines" :key="idx">
                <div class="flex flex-wrap items-end gap-3 mb-3 pb-3 border-b" style="border-color: var(--epms-border);">
                    <div class="flex-1 min-w-[160px]">
                        <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">Material Code</label>
                        <input type="text" :name="`details[${idx}][material_code]`" x-model="l.material_code" placeholder="e.g. MAT001"
                               class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    </div>
                    <div class="flex-1 min-w-[160px]">
                        <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">Material Name</label>
                        <input type="text" :name="`details[${idx}][material_name]`" x-model="l.material_name" placeholder="Optional"
                               class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    </div>
                    <div class="w-24">
                        <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">Qty</label>
                        <input type="number" step="any" :name="`details[${idx}][qty]`" x-model="l.qty"
                               class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    </div>
                    <div class="w-20">
                        <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">UOM</label>
                        <input type="text" :name="`details[${idx}][uom]`" x-model="l.uom"
                               class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    </div>
                    <div class="w-32">
                        <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">Cost Center</label>
                        <input type="text" :name="`details[${idx}][cost_center]`" x-model="l.cost_center"
                               class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    </div>
                    <button type="button" @click="removeLine(idx)"
                            class="mb-0.5 rounded-lg bg-red-50 border border-red-200 p-2 text-red-600 hover:bg-red-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" name="action" value="draft"
                class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                style="border-color: var(--epms-border); color: var(--epms-text);">Save as Draft</button>
        <button type="submit" name="action" value="publish"
                class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">Save &amp; Publish for Approval</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function giPlanForm() {
    return {
        lines: @json($detailsJson),
        addLine() { this.lines.push({ material_code:'', material_name:'', qty:'', uom:'', cost_center:'', order_number:'' }); },
        removeLine(idx) { this.lines.splice(idx, 1); },
    }
}
</script>
@endpush
