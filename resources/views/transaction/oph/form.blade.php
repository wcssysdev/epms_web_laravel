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
<div class="max-w-3xl" x-data="ophForm()">
    <form method="POST"
          action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}">
        @csrf
        @if($item) @method('PUT') @endif

        {{-- Header --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Harvest Details</h2>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                    <x-form.input name="oph_card_id" label="OPH Card ID"
                                  :value="old('oph_card_id', $item?->oph_card_id)"/>

                    <x-form.select name="harvest_method" label="Harvest Method"
                                   :value="old('harvest_method', $item?->harvest_method)">
                        <option value="">— None —</option>
                        @foreach($harvestMethods as $h)
                            <option value="{{ $h->mhm_indicator }}"
                                @selected((string) old('harvest_method', $item?->harvest_method) === (string) $h->mhm_indicator)>
                                {{ $h->mhm_abbreviation }} — {{ $h->mhm_description }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.select name="division_code" label="Division" required
                                   :value="old('division_code', $item?->division_code)">
                        <option value="">— Select Division —</option>
                    </x-form.select>

                    <x-form.select name="block_code" label="Block" required
                                   :value="old('block_code', $item?->block_code)">
                        <option value="">— Select Block —</option>
                    </x-form.select>

                    <x-form.select name="tph_code" label="TPH"
                                   :value="old('tph_code', $item?->tph_code)">
                        <option value="">— Select TPH —</option>
                    </x-form.select>

                    <x-form.input name="platform_no" label="Platform No"
                                  :value="old('platform_no', $item?->platform_no)"/>

                    <x-form.select name="mandor_employee_code" label="Mandor"
                                   :value="old('mandor_employee_code', $item?->mandor_employee_code)">
                        <option value="">— None —</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->employee_code }}"
                                @selected(old('mandor_employee_code', $item?->mandor_employee_code) === $e->employee_code)>
                                {{ $e->employee_code }} — {{ $e->employee_name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.input name="loose_fruits" label="Loose Fruits (kg)" type="number"
                                  :value="old('loose_fruits', $item?->loose_fruits ?? 0)"/>
                </div>
            </div>
        </div>

        {{-- Grading --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Bunch Grading</h2>
                <div class="text-sm">
                    <span style="color: var(--epms-text-muted);">Total Bunches:</span>
                    <span class="font-bold text-primary" x-text="total"></span>
                </div>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-x-5 gap-y-1">
                    @foreach($grading as $col => $label)
                        <div class="form-control mb-3">
                            <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">{{ $label }}</label>
                            <input type="number" min="0" name="{{ $col }}"
                                   value="{{ old($col, $item?->{$col} ?? 0) }}"
                                   @input="recalc()"
                                   class="oph-grade w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                   style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        </div>
                    @endforeach
                </div>
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
function ophForm() {
    return {
        divisions:   @json($divisions),
        blocks:      @json($blocks),
        tphs:        @json($tphs),
        selDivision: @js(old('division_code', $item?->division_code ?? '')),
        selBlock:    @js(old('block_code', $item?->block_code ?? '')),
        selTph:      @js(old('tph_code', $item?->tph_code ?? '')),
        total: 0,
        init() {
            this.divisionEl = this.$root.querySelector('[name="division_code"]');
            this.blockEl    = this.$root.querySelector('[name="block_code"]');
            this.tphEl      = this.$root.querySelector('[name="tph_code"]');
            this.fillDivisions();
            this.divisionEl.addEventListener('change', () => { this.selDivision = this.divisionEl.value; this.selBlock=''; this.fillBlocks(); this.fillTphs(); });
            this.blockEl.addEventListener('change', () => { this.selBlock = this.blockEl.value; this.fillTphs(); });
            this.fillBlocks();
            this.fillTphs();
            this.recalc();
        },
        recalc() {
            let t = 0;
            this.$root.querySelectorAll('.oph-grade').forEach(el => { t += parseInt(el.value || 0, 10) || 0; });
            this.total = t;
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
                opts.map(x => `<option value="${x.tph_code}" ${x.tph_code === this.selTph ? 'selected' : ''}>${x.tph_code}${x.section_code ? ' / '+x.section_code : ''}</option>`).join('');
        },
    }
}
</script>
@endpush
