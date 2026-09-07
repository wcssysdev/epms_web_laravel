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
<div class="max-w-2xl" x-data="workdoneForm()">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Work Completion Details</h2>
        </div>
        <form method="POST"
              action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}"
              class="p-5">
            @csrf
            @if($item) @method('PUT') @endif

            @php $wd = $item?->workdone_date ? \Illuminate\Support\Str::substr((string) $item->workdone_date, 0, 10) : now()->toDateString(); @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="workdone_date" label="Date" type="date" required
                              :value="old('workdone_date', $wd)"/>

                <x-form.select name="activity_code" label="Activity" required
                               :value="old('activity_code', $item?->activity_code)">
                    <option value="">— Select Activity —</option>
                    @foreach($activities as $a)
                        <option value="{{ $a->activity_code }}"
                            @selected(old('activity_code', $item?->activity_code) === $a->activity_code)>
                            {{ $a->activity_code }} — {{ $a->activity_name }}
                        </option>
                    @endforeach
                </x-form.select>

                <x-form.select name="division_code" label="Division" required
                               :value="old('division_code', $item?->division_code)">
                    <option value="">— Select Division —</option>
                </x-form.select>

                <x-form.select name="block_code" label="Block"
                               :value="old('block_code', $item?->block_code)">
                    <option value="">— Select Block —</option>
                </x-form.select>

                <x-form.select name="employee_code" label="Employee" required
                               :value="old('employee_code', $item?->employee_code)">
                    <option value="">— Select Employee —</option>
                    @foreach($employees as $e)
                        <option value="{{ $e->employee_code }}"
                            @selected(old('employee_code', $item?->employee_code) === $e->employee_code)>
                            {{ $e->employee_code }} — {{ $e->employee_name }}
                        </option>
                    @endforeach
                </x-form.select>

                <x-form.input name="qty" label="Quantity Done" type="number" required
                              :value="old('qty', $item?->qty ?? 0)"/>
                <x-form.input name="target_qty" label="Target Quantity" type="number"
                              :value="old('target_qty', $item?->target_qty)"/>
            </div>

            <x-form.input name="description" label="Description"
                          :value="old('description', $item?->description)"/>

            <div class="flex gap-3 pt-4 mt-2 border-t" style="border-color: var(--epms-border);">
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
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
function workdoneForm() {
    return {
        blocks:      @json($blocks),
        divisions:   @json($divisions),
        selDivision: @js(old('division_code', $item?->division_code ?? '')),
        selBlock:    @js(old('block_code', $item?->block_code ?? '')),
        init() {
            this.divisionEl = this.$root.querySelector('[name="division_code"]');
            this.blockEl    = this.$root.querySelector('[name="block_code"]');
            this.fillDivisions();
            this.divisionEl.addEventListener('change', () => { this.selDivision = this.divisionEl.value; this.fillBlocks(); });
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
