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
<div class="max-w-lg" x-data="binForm()">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">{{ $resourceName }} Details</h2>
        </div>
        <form method="POST"
              action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}"
              class="p-5">
            @csrf
            @if($item) @method('PUT') @endif

            {{-- Estate --}}
            <x-form.select name="estate_code" label="Estate" required
                           :value="old('estate_code', $item?->estate_code)">
                <option value="">— Select Estate —</option>
                @foreach($estates as $e)
                    <option value="{{ $e->estate_code }}"
                        @selected(old('estate_code', $item?->estate_code) === $e->estate_code)>
                        {{ $e->estate_code }} — {{ $e->estate_name }}
                    </option>
                @endforeach
            </x-form.select>

            {{-- Division (filtered by estate) --}}
            <x-form.select name="division_code" label="Division" required
                           :value="old('division_code', $item?->division_code)">
                <option value="">— Select Division —</option>
            </x-form.select>

            {{-- BIN Code --}}
            <x-form.input name="bin_code" label="BIN Code" required
                          :value="old('bin_code', $item?->bin_code)"
                          placeholder="e.g. BIN001"/>

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
function binForm() {
    return {
        divisions:   @json($divisions),
        selEstate:   @js(old('estate_code', $item?->estate_code ?? '')),
        selDivision: @js(old('division_code', $item?->division_code ?? '')),

        init() {
            this.estateEl   = this.$root.querySelector('[name="estate_code"]');
            this.divisionEl = this.$root.querySelector('[name="division_code"]');

            this.estateEl.addEventListener('change', () => {
                this.selEstate = this.estateEl.value;
                this.fillDivisions();
            });

            this.fillDivisions();
        },

        fillDivisions() {
            const opts = this.divisions.filter(d => d.estate_code === this.selEstate);
            this.divisionEl.innerHTML = '<option value="">— Select Division —</option>' +
                opts.map(d => `<option value="${d.division_code}" ${d.division_code === this.selDivision ? 'selected' : ''}>${d.division_code} — ${d.division_name ?? ''}</option>`).join('');
        },
    }
}
</script>
@endpush