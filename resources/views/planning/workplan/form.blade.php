@extends('layouts.app')

@section('title', ($workplan ? 'Edit' : 'Add') . ' Workplan')

@section('breadcrumb')
    <li><span class="text-gray-500">Planning /</span></li>
    <li><a href="{{ route('planning.workplan.index') }}" class="text-gray-500 hover:text-primary">Workplan /</a></li>
    <li><span class="font-medium text-primary">{{ $workplan ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($workplan ? 'Edit' : 'Add') . ' Workplan')

@section('page-actions')
    <a href="{{ route('planning.workplan.index') }}"
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
      action="{{ $workplan ? route('planning.workplan.update', $workplan->id) : route('planning.workplan.store') }}"
      x-data="workplanForm()"
      class="max-w-4xl">
    @csrf
    @if($workplan) @method('PUT') @endif

    <div class="grid gap-5 lg:grid-cols-2">

        {{-- ── Plan details ─────────────────────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden lg:col-span-2"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Plan Details</h2>
            </div>
            <div class="p-5 grid gap-x-5 md:grid-cols-2">

                <x-form.input label="Workplan Date" name="workplan_date" type="date" :required="true"
                              :value="$workplan?->workplan_date?->toDateString() ?? $date" />

                <x-form.select label="Division" name="division_code" :required="true">
                    <option value="">— Select Division —</option>
                    @foreach($divisions as $d)
                        <option value="{{ $d->division_code }}"
                            @selected(old('division_code', $workplan?->division_code ?? $defaultDivision) === $d->division_code)>
                            {{ $d->division_code }} - {{ $d->division_name }}
                        </option>
                    @endforeach
                </x-form.select>

                <div class="form-control mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">Activity Group</label>
                    <select x-model="activityGroup" @change="loadActivities()"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">— Select Group —</option>
                        @foreach($activityGroups as $g)
                            <option value="{{ $g }}">{{ $g }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-control mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                        Activity <span class="text-red-500 ml-0.5">*</span>
                    </label>
                    <select name="activity_code" x-model="activityCode" @change="onActivityChange()" required
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary {{ $errors->has('activity_code') ? 'border-red-400' : '' }}"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">— Select Activity —</option>
                        <template x-for="a in activities" :key="a.id">
                            <option :value="a.id" x-text="a.text" :data-uom="a.uom"></option>
                        </template>
                    </select>
                    @error('activity_code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="form-control mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">Block</label>
                    <select name="block_code" x-model="blockCode" @change="onBlockChange()"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">— Select Block (optional) —</option>
                        <template x-for="b in blocks" :key="b.id">
                            <option :value="b.id" x-text="b.text" :data-ha="b.hectarage"></option>
                        </template>
                    </select>
                </div>

                <x-form.input label="Target Quantity" name="total_qty_target" type="number" :required="true"
                              :value="$workplan?->total_qty_target" hint="Cannot exceed block hectarage when a block is chosen." />

                <x-form.input label="Mandays (HK)" name="total_hk" type="number" :required="true"
                              :value="$workplan?->total_hk" />

                <x-form.input label="Order Number" name="order_number"
                              :value="$workplan?->order_number" placeholder="Optional" />

                <x-form.input label="Asset Number (AUC)" name="auc_number"
                              :value="$workplan?->auc_number" placeholder="Optional" />

                <x-form.input label="Cost Center" name="cost_center"
                              :value="$workplan?->cost_center" placeholder="Optional" />

                <x-form.input label="Mandor Code" name="mandor_employee_code"
                              :value="$workplan?->mandor_employee_code" placeholder="Optional" />

                <x-form.input label="Mandor Name" name="mandor_employee_name"
                              :value="$workplan?->mandor_employee_name" placeholder="Optional" />
            </div>
        </div>

        {{-- ── Materials ────────────────────────────────────────────────── --}}
        <div class="rounded-xl border shadow-sm overflow-hidden lg:col-span-2"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Materials</h2>
                <button type="button" @click="addMaterial()"
                        class="flex items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary/20 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Material
                </button>
            </div>
            <div class="p-5">
                <template x-if="materials.length === 0">
                    <p class="text-sm text-center py-4" style="color: var(--epms-text-muted);">No materials added.</p>
                </template>
                <template x-for="(m, idx) in materials" :key="idx">
                    <div class="flex flex-wrap items-end gap-3 mb-3 pb-3 border-b" style="border-color: var(--epms-border);">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">Material Code</label>
                            <input type="text" :name="`materials[${idx}][material_code]`" x-model="m.material_code"
                                   placeholder="e.g. MAT001"
                                   class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">Material Name</label>
                            <input type="text" :name="`materials[${idx}][material_name]`" x-model="m.material_name"
                                   placeholder="Optional"
                                   class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        </div>
                        <div class="w-28">
                            <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">Qty</label>
                            <input type="number" step="any" :name="`materials[${idx}][qty]`" x-model="m.qty"
                                   class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        </div>
                        <button type="button" @click="removeMaterial(idx)"
                                class="mb-0.5 rounded-lg bg-red-50 border border-red-200 p-2 text-red-600 hover:bg-red-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="flex gap-3 mt-5">
        <button type="submit" name="action" value="draft"
                class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                style="border-color: var(--epms-border); color: var(--epms-text);">
            Save as Draft
        </button>
        <button type="submit" name="action" value="publish"
                class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
            Save &amp; Publish for Approval
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function workplanForm() {
    return {
        activityGroup: '',
        activityCode:  @json($workplan?->activity_code ?? ''),
        blockCode:     @json($workplan?->block_code ?? ''),
        activities:    [],
        blocks:        [],
        materials:     @json($materialsJson),

        init() {
            // Preload blocks whenever a division is already chosen (edit mode)
            const div = document.getElementById('division_code');
            if (div) {
                div.addEventListener('change', () => this.loadBlocks(div.value));
                if (div.value) this.loadBlocks(div.value);
            }
        },

        async loadActivities() {
            if (!this.activityGroup) { this.activities = []; return; }
            const res = await fetch(`{{ route('planning.workplan.activities') }}?activity_group_code=${encodeURIComponent(this.activityGroup)}`);
            const json = await res.json();
            this.activities = json.data ?? [];
        },

        async loadBlocks(division) {
            if (!division) { this.blocks = []; return; }
            const res = await fetch(`{{ route('planning.workplan.blocks') }}?division_code=${encodeURIComponent(division)}`);
            const json = await res.json();
            this.blocks = json.data ?? [];
        },

        onActivityChange() {
            const opt = document.querySelector(`select[name="activity_code"] option[value="${this.activityCode}"]`);
            // (UOM could be auto-filled here if a UOM field is added later)
        },

        async onBlockChange() {
            // Optional client-side hint; server still enforces the hectarage guard.
        },

        addMaterial() {
            this.materials.push({ material_code: '', material_name: '', qty: '' });
        },
        removeMaterial(idx) {
            this.materials.splice(idx, 1);
        },
    }
}
</script>
@endpush
