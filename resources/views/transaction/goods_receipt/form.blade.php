@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><a href="{{ route('transactions.goods_receipt.index') }}" class="text-gray-500 hover:text-primary">Goods Receipt</a></li>
    <li><span class="font-medium text-primary">{{ $title }}</span></li>
@endsection

@section('page-title', $title)
@section('page-subtitle', 'Create or update a Goods Receipt transaction')

@section('content')
<div x-data="grForm()" x-init="init()">
    <form method="POST" action="{{ $item ? route('transactions.goods_receipt.update', $item->id) : route('transactions.goods_receipt.store') }}"
          id="gr-form">
        @csrf
        @if($item) @method('PUT') @endif

        {{-- Header card --}}
        <div class="rounded-xl border p-5 mb-5 space-y-4"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="font-semibold text-sm" style="color: var(--epms-text);">Header Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">GR Date <span class="text-red-500">*</span></label>
                    <input type="date" name="gr_date" value="{{ old('gr_date', $item?->gr_date?->toDateString() ?? today()->toDateString()) }}"
                           class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Purchase Order <span class="text-red-500">*</span></label>
                    <select name="po_number" x-model="selectedPo" @change="loadPoDetail()"
                            class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                        <option value="">-- Select PO --</option>
                        @foreach($purchaseOrders as $po)
                        <option value="{{ $po->po_number }}" {{ old('po_number', $item?->po_number) == $po->po_number ? 'selected' : '' }}>
                            {{ $po->po_number }} – {{ $po->vendor_name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Storage Location <span class="text-red-500">*</span></label>
                    <select name="sloc_code"
                            class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                        <option value="">-- Select --</option>
                        @foreach($slocs as $sloc)
                        <option value="{{ $sloc->sloc_code }}" {{ old('sloc_code', $item?->sloc_code) == $sloc->sloc_code ? 'selected' : '' }}>
                            {{ $sloc->sloc_code }} – {{ $sloc->sloc_desc }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Material lines --}}
        <div class="rounded-xl border p-5 mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-sm" style="color: var(--epms-text);">Material Lines (from PO)</h3>
                <button type="button" @click="addLine()"
                        class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-75"
                        style="border-color: var(--epms-border); color: var(--epms-text);">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Manual Line
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--epms-border);">
                            <th class="px-3 py-2 text-left text-xs font-semibold">#</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">Material Code</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">Material Name</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">PO Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">Recv Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">GR Qty <span class="text-red-500">*</span></th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">UOM</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="line.id">
                            <tr class="border-b" style="border-color: var(--epms-border);">
                                <td class="px-3 py-2 text-xs" style="color: var(--epms-text-muted);" x-text="index + 1"></td>
                                <td class="px-3 py-2">
                                    <input type="hidden" :name="`details[${index}][material_code]`" x-model="line.material_code">
                                    <span class="font-mono text-xs" x-text="line.material_code" style="color: var(--epms-text);"></span>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="hidden" :name="`details[${index}][material_name]`" x-model="line.material_name">
                                    <span class="text-xs" x-text="line.material_name" style="color: var(--epms-text);"></span>
                                </td>
                                <td class="px-3 py-2 text-xs text-right" x-text="line.qty_order || '-'" style="color: var(--epms-text-muted);"></td>
                                <td class="px-3 py-2 text-xs text-right" x-text="line.received_qty || '0'" style="color: var(--epms-text-muted);"></td>
                                <td class="px-3 py-2">
                                    <input type="number" :name="`details[${index}][qty]`" x-model="line.qty"
                                           step="0.001" min="0.001" placeholder="0.000"
                                           class="w-24 rounded border px-2 py-1 text-xs outline-none focus:border-primary"
                                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" :name="`details[${index}][uom]`" x-model="line.uom"
                                           placeholder="EA"
                                           class="w-16 rounded border px-2 py-1 text-xs outline-none focus:border-primary"
                                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                                </td>
                                <td class="px-3 py-2">
                                    <button type="button" @click="removeLine(index)"
                                            class="text-red-500 hover:text-red-700 transition" title="Remove">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('transactions.goods_receipt.index') }}"
               class="rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-75"
               style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
            <button type="submit"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">Save</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function grForm() {
    return {
        selectedPo: '{{ old("po_number", $item?->po_number ?? "") }}',
        lines: [],
        nextId: 0,

        init() {
            @if($item && $item->details->count())
            @foreach($item->details as $d)
            this.lines.push({ id: this.nextId++, material_code: '{{ $d->material_code }}', material_name: '{{ $d->material_name }}', qty: '{{ $d->qty }}', uom: '{{ $d->uom }}', qty_order: null, received_qty: null });
            @endforeach
            @else
            if (this.selectedPo) this.loadPoDetail();
            else this.addLine();
            @endif
        },

        addLine() {
            this.lines.push({ id: this.nextId++, material_code: '', material_name: '', qty: '', uom: '', qty_order: null, received_qty: null });
        },

        removeLine(index) {
            if (this.lines.length > 1) this.lines.splice(index, 1);
        },

        async loadPoDetail() {
            if (!this.selectedPo) { this.addLine(); return; }
            try {
                const res = await fetch(`{{ route('transactions.goods_receipt.po_detail') }}?po_number=${encodeURIComponent(this.selectedPo)}`);
                const data = await res.json();
                if (data.length) {
                    this.lines = data.map((d, i) => ({
                        id: i,
                        material_code: d.material_code,
                        material_name: d.material_name,
                        qty: d.remaining_qty,
                        uom: d.uom,
                        qty_order: d.qty_order,
                        received_qty: d.received_qty,
                    }));
                    this.nextId = data.length;
                } else {
                    this.lines = [];
                    this.addLine();
                }
            } catch(e) { this.addLine(); }
        }
    }
}
</script>
@endpush
