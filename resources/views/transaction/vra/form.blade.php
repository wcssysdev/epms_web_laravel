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
<div class="max-w-2xl" x-data="{
    selectedType: '{{ old('vra_type', '') }}',
    selectedOrder: '{{ old('order_number', $item?->order_number ?? '') }}',
    vraOrders: @js($vraOrders),
    filteredOrders() {
        if (!this.selectedType) return this.vraOrders;
        return this.vraOrders.filter(v => v.object_type === this.selectedType);
    }
}">
<form method="POST"
      action="{{ $item ? route($routePrefix.'.update',$item->id) : route($routePrefix.'.store') }}">
    @csrf
    @if($item) @method('PUT') @endif

    <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color:var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color:var(--epms-text);">VRA Details</h2>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-5">

            <x-form.input name="vra_date" label="Posting Date" type="date" required
                          :value="old('vra_date', $item?->vra_date?->format('Y-m-d'))"/>

            {{-- VRA Object Type filter --}}
            <div>
                <label class="block text-sm font-medium mb-1" style="color:var(--epms-text);">Object Type</label>
                <select x-model="selectedType"
                        class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                        style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
                    <option value="">— All Types —</option>
                    @foreach($vraTypes as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            {{-- VRA Order Number (cascades from type) --}}
            <div>
                <label class="block text-sm font-medium mb-1" style="color:var(--epms-text);">Order Number <span class="text-red-500">*</span></label>
                <select name="order_number" x-model="selectedOrder" required
                        class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                        style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
                    <option value="">— Select Order —</option>
                    <template x-for="v in filteredOrders()" :key="v.vra_order_number">
                        <option :value="v.vra_order_number"
                                :selected="selectedOrder === v.vra_order_number"
                                x-text="v.vra_order_number + ' — ' + (v.license_number ?? '')"></option>
                    </template>
                </select>
            </div>

            <x-form.input name="license_number" label="License Number" required
                          :value="old('license_number', $item?->license_number)"/>

            <x-form.input name="meas_point" label="Measurement Point"
                          :value="old('meas_point', $item?->meas_point)"/>

            <x-form.input name="reading_value" label="Reading Value / Actual" type="number" step="0.01" required
                          :value="old('reading_value', $item?->reading_value)"/>

            <x-form.input name="confirmation_text" label="Confirmation Text"
                          :value="old('confirmation_text', $item?->confirmation_text)"/>

            <div class="md:col-span-2">
                <x-form.input name="remark" label="Remark"
                              :value="old('remark', $item?->remark)"/>
            </div>
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
