@extends('layouts.app')

@section('title', ($plan ? 'Edit' : 'Add') . ' Harvesting Plan')

@section('breadcrumb')
    <li><span class="text-gray-500">Planning /</span></li>
    <li><a href="{{ route('planning.harvesting_plan.index') }}" class="text-gray-500 hover:text-primary">Harvesting Plan /</a></li>
    <li><span class="font-medium text-primary">{{ $plan ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($plan ? 'Edit' : 'Add') . ' Harvesting Plan')

@section('page-actions')
    <a href="{{ route('planning.harvesting_plan.index') }}"
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
      action="{{ $plan ? route('planning.harvesting_plan.update', $plan->id) : route('planning.harvesting_plan.store') }}"
      x-data="harvestingForm()"
      class="max-w-2xl">
    @csrf
    @if($plan) @method('PUT') @endif

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Plan Details</h2>
        </div>
        <div class="p-5 grid gap-x-5 md:grid-cols-2">

            <x-form.input label="Plan Date" name="plan_date" type="date" :required="true"
                          :value="$plan?->plan_date?->toDateString() ?? $date" />

            <x-form.select label="Division" name="division_code" :required="true">
                <option value="">— Select Division —</option>
                @foreach($divisions as $d)
                    <option value="{{ $d->division_code }}" @selected(old('division_code', $plan?->division_code) === $d->division_code)>
                        {{ $d->division_code }} - {{ $d->division_name }}
                    </option>
                @endforeach
            </x-form.select>

            <div class="form-control mb-4">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
                    Block <span class="text-red-500 ml-0.5">*</span>
                </label>
                <select name="block_code" x-model="blockCode" @change="onBlockChange()" required
                        class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary {{ $errors->has('block_code') ? 'border-red-400' : '' }}"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    <option value="">— Select Block —</option>
                    <template x-for="b in blocks" :key="b.id">
                        <option :value="b.id" x-text="b.text" :data-ha="b.hectarage"></option>
                    </template>
                </select>
                @error('block_code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <x-form.input label="Hectarage (HA)" name="ha" :value="$plan?->ha" placeholder="Auto-filled from block" />

            <x-form.input label="Target Quantity" name="qty_target" type="number" :required="true" :value="$plan?->qty_target" />

            <x-form.input label="Mandays (HK)" name="total_hk" type="number" :required="true" :value="$plan?->total_hk" />

            <x-form.input label="Assistant Code" name="assistant_emp_code" :value="$plan?->assistant_emp_code" placeholder="Optional" />

            <x-form.input label="Assistant Name" name="assistant_emp_name" :value="$plan?->assistant_emp_name" placeholder="Optional" />
        </div>

        <div class="flex gap-3 px-5 py-4 border-t" style="border-color: var(--epms-border);">
            <button type="submit" name="action" value="draft"
                    class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                    style="border-color: var(--epms-border); color: var(--epms-text);">Save as Draft</button>
            <button type="submit" name="action" value="publish"
                    class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">Save &amp; Publish</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
function harvestingForm() {
    return {
        blockCode: @json($plan?->block_code ?? ''),
        blocks: [],
        init() {
            const div = document.getElementById('division_code');
            if (div) {
                div.addEventListener('change', () => this.loadBlocks(div.value));
                if (div.value) this.loadBlocks(div.value);
            }
        },
        async loadBlocks(division) {
            if (!division) { this.blocks = []; return; }
            const res = await fetch(`{{ route('planning.harvesting_plan.blocks') }}?division_code=${encodeURIComponent(division)}`);
            const json = await res.json();
            this.blocks = json.data ?? [];
        },
        onBlockChange() {
            const opt = document.querySelector(`select[name="block_code"] option[value="${this.blockCode}"]`);
            const ha = opt?.dataset?.ha;
            if (ha) document.getElementById('ha').value = ha;
        },
    }
}
</script>
@endpush
