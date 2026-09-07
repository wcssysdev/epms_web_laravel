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
<div class="max-w-4xl" x-data="cfdnForm()">
    <form method="POST"
          action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}">
        @csrf
        @if($item) @method('PUT') @endif

        {{-- Header --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Delivery Note (Coconut) Details</h2>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                    <x-form.select name="division_code" label="Division" required
                                   :value="old('division_code', $item?->division_code)">
                        <option value="">— Select Division —</option>
                        @foreach($divisions as $d)
                            <option value="{{ $d->division_code }}"
                                @selected(old('division_code', $item?->division_code) === $d->division_code)>
                                {{ $d->division_code }} — {{ $d->division_name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.input name="delivery_note" label="Delivery Note No"
                                  :value="old('delivery_note', $item?->delivery_note)"/>

                    <x-form.input name="fdn_card_id" label="FDN Card ID"
                                  :value="old('fdn_card_id', $item?->fdn_card_id)"/>

                    <x-form.select name="destination" label="Destination"
                                   :value="old('destination', $item?->destination)">
                        <option value="">— None —</option>
                        @foreach($destinations as $dst)
                            <option value="{{ $dst->destination_code }}"
                                @selected(old('destination', $item?->destination) === $dst->destination_code)>
                                {{ $dst->destination_code }} — {{ $dst->destination_name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.select name="kerani_kirim_emp_code" label="Kerani Kirim"
                                   :value="old('kerani_kirim_emp_code', $item?->kerani_kirim_emp_code)">
                        <option value="">— None —</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->employee_code }}"
                                @selected(old('kerani_kirim_emp_code', $item?->kerani_kirim_emp_code) === $e->employee_code)>
                                {{ $e->employee_code }} — {{ $e->employee_name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.select name="vehicle_vendor_code" label="Vehicle Vendor"
                                   :value="old('vehicle_vendor_code', $item?->vehicle_vendor_code)">
                        <option value="">— None —</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->vendor_code }}"
                                @selected(old('vehicle_vendor_code', $item?->vehicle_vendor_code) === $v->vendor_code)>
                                {{ $v->vendor_code }} — {{ $v->vendor_name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.input name="driver_name" label="Driver Name"
                                  :value="old('driver_name', $item?->driver_name)"/>
                    <x-form.input name="license_number" label="Vehicle License No"
                                  :value="old('license_number', $item?->license_number)"/>
                    <x-form.input name="sales_order_no" label="Sales Order No"
                                  :value="old('sales_order_no', $item?->sales_order_no)"/>
                    <x-form.input name="sales_order_item" label="Sales Order Item"
                                  :value="old('sales_order_item', $item?->sales_order_item)"/>
                    <x-form.input name="bruto" label="Bruto (kg)" type="number"
                                  :value="old('bruto', $item?->bruto)"/>
                    <x-form.input name="tarra" label="Tarra (kg)" type="number"
                                  :value="old('tarra', $item?->tarra)"/>
                </div>
            </div>
        </div>

        {{-- Harvesting Chit detail lines --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Delivered Harvesting Chits</h2>
                <div class="flex items-center gap-4 text-sm">
                    <span style="color: var(--epms-text-muted);">Lines: <span class="font-bold" style="color: var(--epms-text);" x-text="details.length"></span></span>
                    <span style="color: var(--epms-text-muted);">Customer Qty: <span class="font-bold text-primary" x-text="totalQty"></span></span>
                    <button type="button" @click="loadAvailable()"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                            style="border-color: var(--epms-border); color: var(--epms-text);">Load Available Chits</button>
                </div>
            </div>
            <div class="p-5">
                <div x-show="available.length > 0" class="mb-4 rounded-lg border p-3" style="border-color: var(--epms-border);">
                    <p class="text-xs font-medium mb-2" style="color: var(--epms-text-muted);">Available Harvesting Chits — click to add:</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="o in available" :key="o.id">
                            <button type="button" @click="addDetail(o)"
                                    class="rounded-lg border px-2.5 py-1 text-xs transition hover:opacity-80"
                                    style="border-color: var(--epms-border); color: var(--epms-text);"
                                    x-text="`${o.oph_card_id ?? o.id} · blk ${o.block_code ?? '-'} · ${o.nuts_total ?? 0} nuts`"></button>
                        </template>
                    </div>
                </div>

                <table class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-2 py-2 text-left text-xs uppercase" style="color: var(--epms-text-muted);">Chit / Card</th>
                            <th class="px-2 py-2 text-left text-xs uppercase" style="color: var(--epms-text-muted);">Customer Nut Qty</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(d, i) in details" :key="i">
                            <tr class="border-b" style="border-color: var(--epms-border);">
                                <td class="px-2 py-1.5">
                                    <span x-text="d.coconut_oph_card_id || d.coconut_oph_id"></span>
                                    <input type="hidden" :name="`details[${i}][coconut_oph_id]`" :value="d.coconut_oph_id">
                                    <input type="hidden" :name="`details[${i}][coconut_oph_card_id]`" :value="d.coconut_oph_card_id">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="number" step="0.01" min="0" :name="`details[${i}][total_customer_nut_qty]`"
                                           x-model="d.total_customer_nut_qty" @input="recalc()"
                                           class="w-32 rounded border px-2 py-1 text-sm"
                                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                                </td>
                                <td class="px-2 py-1.5 text-right">
                                    <button type="button" @click="removeDetail(i)"
                                            class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-600 hover:bg-red-100">Remove</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="details.length === 0">
                            <td colspan="3" class="px-2 py-3 text-center text-xs" style="color: var(--epms-text-muted);">
                                No chit lines. Choose a division and click "Load Available Chits".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <x-form.input name="remark" label="Remark" :value="old('remark', $item?->remark)"/>

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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function cfdnForm() {
    return {
        details: @js($details->map(fn($d) => [
            'coconut_oph_id' => $d->coconut_oph_id, 'coconut_oph_card_id' => $d->coconut_oph_card_id,
            'total_customer_nut_qty' => $d->total_customer_nut_qty,
        ])->values()),
        available: [],
        totalQty: 0,
        availableUrl: '{{ route($routePrefix.".available-chit") }}',
        init() { this.recalc(); },
        recalc() {
            this.totalQty = this.details.reduce((s, d) => s + (parseFloat(d.total_customer_nut_qty || 0) || 0), 0);
        },
        loadAvailable() {
            const div = this.$root.querySelector('[name="division_code"]').value;
            $.get(this.availableUrl, { division_code: div }, (res) => {
                const existing = this.details.map(d => String(d.coconut_oph_id));
                this.available = (res.data || []).filter(o => !existing.includes(String(o.id)));
            });
        },
        addDetail(o) {
            this.details.push({ coconut_oph_id: o.id, coconut_oph_card_id: o.oph_card_id, total_customer_nut_qty: o.nuts_total ?? 0 });
            this.available = this.available.filter(a => a.id !== o.id);
            this.recalc();
        },
        removeDetail(i) { this.details.splice(i, 1); this.recalc(); },
    }
}
</script>
@endpush
