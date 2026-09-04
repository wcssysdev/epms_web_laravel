@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $resourceName)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $resourceName }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($item ? 'Edit' : 'Add') . ' ' . $resourceName)

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
<div class="max-w-2xl" x-data="reportOphForm()">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Master OPH Rate</h2>
        </div>
        <form method="POST"
              action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}"
              class="p-5">
            @csrf
            @if($item) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                @php $per = $item?->period ? \Illuminate\Support\Str::substr((string) $item->period, 0, 10) : ''; @endphp
                <x-form.input name="period" label="Period" type="date" required
                              :value="old('period', $per)"/>

                <div></div>

                <x-form.select name="division_code" label="Division" required
                               :value="old('division_code', $item?->division_code)">
                    <option value="">— Select Division —</option>
                </x-form.select>

                <x-form.select name="block_code" label="Block" required
                               :value="old('block_code', $item?->block_code)">
                    <option value="">— Select Block —</option>
                </x-form.select>

                <x-form.input name="basis" label="Basis" type="number" required
                              :value="old('basis', $item?->basis ?? 0)"/>
                <x-form.input name="gandeng" label="Gandeng" type="number" required
                              :value="old('gandeng', $item?->gandeng ?? 0)"/>
                <x-form.input name="premi_basis" label="Premi Rate > Basis" type="number" required
                              :value="old('premi_basis', $item?->premi_basis ?? 0)"/>
                <x-form.input name="premi_non_basis" label="Premi Rate < Basis" type="number" required
                              :value="old('premi_non_basis', $item?->premi_non_basis ?? 0)"/>
                <x-form.input name="brondolan_rate_1" label="Brondolan Rate 1" type="number" required
                              :value="old('brondolan_rate_1', $item?->brondolan_rate_1 ?? 0)"/>
                <x-form.input name="brondolan_rate_2" label="Brondolan Rate 2" type="number" required
                              :value="old('brondolan_rate_2', $item?->brondolan_rate_2 ?? 0)"/>
                <x-form.input name="hk_rate" label="HK Rate / Minimum" type="number" required
                              :value="old('hk_rate', $item?->hk_rate ?? 0)"/>
            </div>

            <div class="flex gap-3 pt-4 mt-2 border-t" style="border-color: var(--epms-border);">
                <button type="submit"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
                    {{ $item ? 'Update' : 'Save' }}
                </button>
                <a href="{{ route($routePrefix.'.index') }}"
                   class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                   style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function reportOphForm() {
    return {
        divisions: @json($divisions),
        blocks:    @json($blocks),
        selDivision: @js(old('division_code', $item?->division_code ?? '')),
        selBlock:    @js(old('block_code', $item?->block_code ?? '')),

        init() {
            this.divisionEl = this.$root.querySelector('[name="division_code"]');
            this.blockEl    = this.$root.querySelector('[name="block_code"]');
            this.divisionEl.addEventListener('change', () => { this.selDivision = this.divisionEl.value; this.fillBlocks(); });

            this.fillDivisions();
            this.fillBlocks();
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
    }
}
</script>
@endpush
