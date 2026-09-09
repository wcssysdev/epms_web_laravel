@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $title)
@section('breadcrumb')
    <li><a href="{{ route($routePrefix.'.index') }}" class="text-gray-500 hover:text-primary">{{ $title }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection
@section('page-title', ($item ? 'Edit' : 'Add') . ' ' . $title)
@section('page-actions')
    <a href="{{ route($routePrefix.'.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:opacity-80"
       style="border-color:var(--epms-border);color:var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>Back
    </a>
@endsection

@section('content')
<div class="max-w-2xl" x-data="pcForm()">
<form method="POST"
      action="{{ $item ? route($routePrefix.'.update',$item->id) : route($routePrefix.'.store') }}">
    @csrf
    @if($item) @method('PUT') @endif

    {{-- Header --}}
    <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color:var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color:var(--epms-text);">Platform Checking</h2>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-5">

            <x-form.input name="check_date" label="Check Date" type="date" required
                          :value="old('check_date', $item?->check_date?->format('Y-m-d'))"/>

            <x-form.select name="division_code" label="Division" required
                           :value="old('division_code', $item?->division_code)">
                <option value="">— Select Division —</option>
                @foreach($divisions as $d)
                    <option value="{{ $d->division_code }}" @selected(old('division_code',$item?->division_code)===$d->division_code)>
                        {{ $d->division_code }} — {{ $d->division_name }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.select name="block_code" label="Block"
                           :value="old('block_code', $item?->block_code)">
                <option value="">— None —</option>
                @foreach($blocks as $b)
                    <option value="{{ $b->block_code }}" @selected(old('block_code',$item?->block_code)===$b->block_code)>
                        {{ $b->block_code }} — {{ $b->block_name }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.select name="tph_code" label="TPH"
                           :value="old('tph_code', $item?->tph_code)">
                <option value="">— None —</option>
                @foreach($tphs as $t)
                    <option value="{{ $t->tph_code }}" @selected(old('tph_code',$item?->tph_code)===$t->tph_code)>
                        {{ $t->tph_code }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.input name="check_status" label="Check Status" required
                          placeholder="e.g. PASS / FAIL"
                          :value="old('check_status', $item?->check_status)"/>

            <div class="md:col-span-2">
                <x-form.input name="notes" label="Notes"
                              :value="old('notes', $item?->notes)"/>
            </div>
        </div>
    </div>

    {{-- Detail lines --}}
    <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color:var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color:var(--epms-text);">Check Items</h2>
            <button type="button" @click="addRow()"
                    class="rounded-lg border px-3 py-1.5 text-xs font-medium hover:opacity-80"
                    style="border-color:var(--epms-border);color:var(--epms-text);">+ Add Item</button>
        </div>
        <div class="p-5">
            <template x-for="(row, i) in rows" :key="i">
                <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                    <input type="text" :name="`detail_type[${i}]`" x-model="row.type"
                           placeholder="Type (e.g. Cleanliness)"
                           class="col-span-5 rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                           style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
                    <input type="text" :name="`detail_value[${i}]`" x-model="row.value"
                           placeholder="Value / Result"
                           class="col-span-5 rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                           style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
                    <button type="button" @click="removeRow(i)"
                            class="col-span-2 rounded-lg border border-red-300 bg-red-50 px-2 py-2 text-xs font-medium text-red-600 hover:bg-red-100 transition">Remove</button>
                </div>
            </template>
            <p x-show="rows.length === 0" class="text-xs" style="color:var(--epms-text-muted);">No check items. Click "+ Add Item" to add.</p>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit"
                class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
            {{ $item ? 'Update' : 'Save' }}
        </button>
        <a href="{{ route($routePrefix.'.index') }}"
           class="rounded-lg border px-5 py-2.5 text-sm font-medium hover:opacity-80 transition"
           style="border-color:var(--epms-border);color:var(--epms-text);">Cancel</a>
    </div>
</form>
</div>
@endsection

@push('scripts')
<script>
function pcForm() {
    return {
        rows: @js($details->map(fn($d) => ['type' => $d->detail_type, 'value' => $d->detail_value])->values()),
        addRow()  { this.rows.push({ type: '', value: '' }); },
        removeRow(i) { this.rows.splice(i, 1); },
    };
}
</script>
@endpush
