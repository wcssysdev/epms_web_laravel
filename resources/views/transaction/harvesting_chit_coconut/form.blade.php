@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $title)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $title }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($item ? 'Edit' : 'Add') . ' ' . $title)

@section('page-actions')
    <a href="{{ route($routePrefix . '.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
<div class="max-w-3xl" x-data="chitForm()">
    <form method="POST"
          action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}">
        @csrf
        @if($item) @method('PUT') @endif

        {{-- Header --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Harvesting Chit Details</h2>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                    <x-form.select name="division_code" label="Division" required
                                   :value="old('division_code', $item?->division_code)">
                        <option value="">— Select Division —</option>
                    </x-form.select>

                    <x-form.select name="block_code" label="Block" required
                                   :value="old('block_code', $item?->block_code)">
                        <option value="">— Select Block —</option>
                    </x-form.select>

                    <x-form.select name="tph_code" label="TPH" required
                                   :value="old('tph_code', $item?->tph_code)">
                        <option value="">— Select TPH —</option>
                    </x-form.select>

                    <x-form.select name="checker_employee_code" label="Checker" required
                                   :value="old('checker_employee_code', $item?->checker_employee_code)">
                        <option value="">— Select Checker —</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->employee_code }}"
                                @selected(old('checker_employee_code', $item?->checker_employee_code) === $e->employee_code)>
                                {{ $e->employee_code }} — {{ $e->employee_name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.input name="oph_card_id" label="Chit / Card ID"
                                  :value="old('oph_card_id', $item?->oph_card_id)"/>
                    <x-form.input name="gang_code" label="Gang Code"
                                  :value="old('gang_code', $item?->gang_code)"/>
                    <x-form.input name="nuts_total" label="Nuts Total" type="number"
                                  :value="old('nuts_total', $item?->nuts_total ?? 0)"/>
                </div>
            </div>
        </div>

        {{-- Material detail lines (customer nut qty) --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Customer Material Lines</h2>
                <button type="button" @click="addDetail()"
                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                        style="border-color: var(--epms-border); color: var(--epms-text);">+ Add Line</button>
            </div>
            <div class="p-5">
                <template x-for="(d, i) in details" :key="i">
                    <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                        <select :name="`details[${i}][material_code]`" x-model="d.material_code"
                                class="col-span-8 rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            <option value="">— Select Material —</option>
                            @foreach($materials as $m)
                                <option value="{{ $m->material_code }}">{{ $m->material_code }} — {{ $m->material_desc }}</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.01" min="0" :name="`details[${i}][customer_nut_qty]`"
                               x-model="d.customer_nut_qty" placeholder="Nut Qty"
                               class="col-span-2 rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <button type="button" @click="removeDetail(i)"
                                class="col-span-2 rounded-lg border border-red-300 bg-red-50 px-2 py-2 text-xs font-medium text-red-600 hover:bg-red-100 transition">Remove</button>
                    </div>
                </template>
                <p x-show="details.length === 0" class="text-xs" style="color: var(--epms-text-muted);">No material lines.</p>
            </div>
        </div>

        {{-- Harvester persons --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Harvesters</h2>
                <button type="button" @click="addPerson()"
                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                        style="border-color: var(--epms-border); color: var(--epms-text);">+ Add Harvester</button>
            </div>
            <div class="p-5">
                <template x-for="(p, i) in persons" :key="i">
                    <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                        <select :name="`persons[${i}][employee_code]`" x-model="p.employee_code"
                                class="col-span-10 rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            <option value="">— Select Harvester —</option>
                            @foreach($employees as $e)
                                <option value="{{ $e->employee_code }}">{{ $e->employee_code }} — {{ $e->employee_name }}</option>
                            @endforeach
                        </select>
                        <button type="button" @click="removePerson(i)"
                                class="col-span-2 rounded-lg border border-red-300 bg-red-50 px-2 py-2 text-xs font-medium text-red-600 hover:bg-red-100 transition">Remove</button>
                    </div>
                </template>
                <p x-show="persons.length === 0" class="text-xs" style="color: var(--epms-text-muted);">No harvesters added.</p>
            </div>
        </div>

        <x-form.input name="notes" label="Notes" :value="old('notes', $item?->notes)"/>

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
                {{ $item ? 'Update' : 'Save' }}
            </button>
            <a href="{{ route($routePrefix.'.index') }}"
               class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
               style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function chitForm() {
    return {
        divisions:   @json($divisions),
        blocks:      @json($blocks),
        tphs:        @json($tphs),
        selDivision: @js(old('division_code', $item?->division_code ?? '')),
        selBlock:    @js(old('block_code', $item?->block_code ?? '')),
        selTph:      @js(old('tph_code', $item?->tph_code ?? '')),
        details: @js($details->map(fn($d) => ['material_code' => $d->material_code, 'customer_nut_qty' => $d->customer_nut_qty])->values()),
        persons: @js($persons->map(fn($p) => ['employee_code' => $p->employee_code])->values()),
        init() {
            this.divisionEl = this.$root.querySelector('[name="division_code"]');
            this.blockEl    = this.$root.querySelector('[name="block_code"]');
            this.tphEl      = this.$root.querySelector('[name="tph_code"]');
            this.fillDivisions();
            this.divisionEl.addEventListener('change', () => { this.selDivision = this.divisionEl.value; this.selBlock=''; this.fillBlocks(); this.fillTphs(); });
            this.blockEl.addEventListener('change', () => { this.selBlock = this.blockEl.value; this.fillTphs(); });
            this.fillBlocks();
            this.fillTphs();
        },
        fillDivisions() {
            this.divisionEl.innerHTML = '<option value="">— Select Division —</option>' +
                this.divisions.map(d => `<option value="${d.division_code}" ${d.division_code === this.selDivision ? 'selected' : ''}>${d.division_code} — ${d.division_name ?? ''}</option>`).join('');
        },
        fillBlocks() {
            const opts = this.blocks.filter(b => b.division_code === this.selDivision);
            this.blockEl.innerHTML = '<option value="">— Select Block —</option>' +
                opts.map(b => `<option value="${b.block_code}" ${b.block_code === this.selBlock ? 'selected' : ''}>${b.block_code} — ${b.block_name ?? ''}</option>`).join('');
        },
        fillTphs() {
            const opts = this.tphs.filter(x => x.division_code === this.selDivision && x.block_code === this.selBlock);
            this.tphEl.innerHTML = '<option value="">— Select TPH —</option>' +
                opts.map(x => `<option value="${x.tph_code}" ${x.tph_code === this.selTph ? 'selected' : ''}>${x.tph_code}</option>`).join('');
        },
        addDetail() { this.details.push({ material_code: '', customer_nut_qty: 0 }); },
        removeDetail(i) { this.details.splice(i, 1); },
        addPerson() { this.persons.push({ employee_code: '' }); },
        removePerson(i) { this.persons.splice(i, 1); },
    }
}
</script>
@endpush
