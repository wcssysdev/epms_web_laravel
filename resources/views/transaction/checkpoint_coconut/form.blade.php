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
<div class="max-w-4xl" x-data="cpCoconutForm()">
    <form method="POST"
          action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}">
        @csrf
        @if($item) @method('PUT') @endif

        {{-- Header --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Checkpoint (Coconut) Details</h2>
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

                    <x-form.input name="delivery_note" label="Delivery Note"
                                  :value="old('delivery_note', $item?->delivery_note)"/>

                    <x-form.select name="receiving_point_code" label="Receiving Point / Ramp"
                                   :value="old('receiving_point_code', $item?->receiving_point_code)">
                        <option value="">— None —</option>
                        @foreach($receivingPoints as $rp)
                            <option value="{{ $rp->receiving_point_code }}"
                                @selected(old('receiving_point_code', $item?->receiving_point_code) === $rp->receiving_point_code)>
                                {{ $rp->receiving_point_code }}
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

                    <x-form.select name="vendor_code" label="Vendor / Transporter"
                                   :value="old('vendor_code', $item?->vendor_code)">
                        <option value="">— None —</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->vendor_code }}"
                                @selected(old('vendor_code', $item?->vendor_code) === $v->vendor_code)>
                                {{ $v->vendor_code }} — {{ $v->vendor_name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.input name="license_number" label="Vehicle License No"
                                  :value="old('license_number', $item?->license_number)"/>
                    <x-form.input name="seal_code" label="Seal Code"
                                  :value="old('seal_code', $item?->seal_code)"/>
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
                    <span style="color: var(--epms-text-muted);">Chits: <span class="font-bold" style="color: var(--epms-text);" x-text="details.length"></span></span>
                    <span style="color: var(--epms-text-muted);">Nuts: <span class="font-bold text-primary" x-text="totalNuts"></span></span>
                    <button type="button" @click="loadAvailable()"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                            style="border-color: var(--epms-border); color: var(--epms-text);">Load Available Chits</button>
                </div>
            </div>
            <div class="p-5">
                {{-- Available chit picker --}}
                <div x-show="available.length > 0" class="mb-4 rounded-lg border p-3" style="border-color: var(--epms-border);">
                    <p class="text-xs font-medium mb-2" style="color: var(--epms-text-muted);">Available Harvesting Chits for the selected division — click to add:</p>
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
                            <th class="px-2 py-2 text-left text-xs uppercase" style="color: var(--epms-text-muted);">Block</th>
                            <th class="px-2 py-2 text-left text-xs uppercase" style="color: var(--epms-text-muted);">TPH</th>
                            <th class="px-2 py-2 text-left text-xs uppercase" style="color: var(--epms-text-muted);">Nuts</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(d, i) in details" :key="i">
                            <tr class="border-b" style="border-color: var(--epms-border);">
                                <td class="px-2 py-1.5">
                                    <span x-text="d.oph_card_id || d.oph_id"></span>
                                    <input type="hidden" :name="`details[${i}][oph_id]`" :value="d.oph_id">
                                </td>
                                <td class="px-2 py-1.5" x-text="d.oph_block_code || '-'"></td>
                                <td class="px-2 py-1.5" x-text="d.oph_tph_code || '-'"></td>
                                <td class="px-2 py-1.5 font-medium" x-text="d.bunches_delivered"></td>
                                <td class="px-2 py-1.5 text-right">
                                    <button type="button" @click="removeDetail(i)"
                                            class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-600 hover:bg-red-100">Remove</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="details.length === 0">
                            <td colspan="5" class="px-2 py-3 text-center text-xs" style="color: var(--epms-text-muted);">
                                No chits. Choose a division and click "Load Available Chits".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Loaders --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Loaders</h2>
                <button type="button" @click="addLoader()"
                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                        style="border-color: var(--epms-border); color: var(--epms-text);">+ Add Loader</button>
            </div>
            <div class="p-5">
                <template x-for="(l, i) in loaders" :key="i">
                    <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                        <select :name="`loaders[${i}][employee_code]`" x-model="l.employee_code"
                                class="col-span-8 rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                                style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                            <option value="">— Select Loader —</option>
                            @foreach($employees as $e)
                                <option value="{{ $e->employee_code }}">{{ $e->employee_code }} — {{ $e->employee_name }}</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.01" min="0" :name="`loaders[${i}][percentage]`"
                               x-model="l.percentage" placeholder="%"
                               class="col-span-2 rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                               style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <button type="button" @click="removeLoader(i)"
                                class="col-span-2 rounded-lg border border-red-300 bg-red-50 px-2 py-2 text-xs font-medium text-red-600 hover:bg-red-100 transition">Remove</button>
                    </div>
                </template>
                <p x-show="loaders.length === 0" class="text-xs" style="color: var(--epms-text-muted);">No loaders added.</p>
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
function cpCoconutForm() {
    return {
        details: @js($details->map(fn($d) => [
            'oph_id' => $d->oph_id, 'oph_card_id' => $d->oph_card_id, 'oph_block_code' => $d->oph_block_code,
            'oph_tph_code' => $d->oph_tph_code, 'bunches_delivered' => $d->bunches_delivered,
        ])->values()),
        loaders: @js($loaders->map(fn($l) => ['employee_code' => $l->employee_code, 'percentage' => $l->percentage])->values()),
        available: [],
        totalNuts: 0,
        availableUrl: '{{ route($routePrefix.".available-chit") }}',
        init() { this.recalc(); },
        recalc() {
            this.totalNuts = this.details.reduce((s, d) => s + (parseInt(d.bunches_delivered || 0, 10) || 0), 0);
        },
        loadAvailable() {
            const div = this.$root.querySelector('[name="division_code"]').value;
            $.get(this.availableUrl, { division_code: div }, (res) => {
                const existing = this.details.map(d => String(d.oph_id));
                this.available = (res.data || []).filter(o => !existing.includes(String(o.id)));
            });
        },
        addDetail(o) {
            this.details.push({
                oph_id: o.id, oph_card_id: o.oph_card_id, oph_block_code: o.block_code,
                oph_tph_code: o.tph_code, bunches_delivered: o.nuts_total ?? 0,
            });
            this.available = this.available.filter(a => a.id !== o.id);
            this.recalc();
        },
        removeDetail(i) { this.details.splice(i, 1); this.recalc(); },
        addLoader() { this.loaders.push({ employee_code: '', percentage: 0 }); },
        removeLoader(i) { this.loaders.splice(i, 1); },
    }
}
</script>
@endpush
