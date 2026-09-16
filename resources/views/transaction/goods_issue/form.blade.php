@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><a href="{{ route('transactions.goods_issue.index') }}" class="text-gray-500 hover:text-primary">Goods Issue</a></li>
    <li><span class="font-medium text-primary">{{ $title }}</span></li>
@endsection

@section('page-title', $title)
@section('page-subtitle', 'Create or update a Goods Issue transaction')

@section('content')
<div x-data="giForm()" x-init="init()">
    <form method="POST" action="{{ $item ? route('transactions.goods_issue.update', $item->id) : route('transactions.goods_issue.store') }}"
          id="gi-form">
        @csrf
        @if($item) @method('PUT') @endif

        {{-- Header card --}}
        <div class="rounded-xl border p-5 mb-5 space-y-4"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="font-semibold text-sm" style="color: var(--epms-text);">Header Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- GI Date --}}
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">GI Date <span class="text-red-500">*</span></label>
                    <input type="date" name="gi_date" value="{{ old('gi_date', $item?->gi_date?->toDateString() ?? today()->toDateString()) }}"
                           class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                    @error('gi_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Movement Type --}}
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Movement Type <span class="text-red-500">*</span></label>
                    <select name="movement_type" x-model="movementType"
                            class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                        <option value="">-- Select --</option>
                        @foreach($movementTypes as $mt)
                        <option value="{{ $mt->mvt_type_code }}" {{ old('movement_type', $item?->movement_type) == $mt->mvt_type_code ? 'selected' : '' }}>
                            {{ $mt->mvt_type_code }} – {{ $mt->mvt_type_desc }}
                        </option>
                        @endforeach
                    </select>
                    @error('movement_type')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Storage Location --}}
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
                    @error('sloc_code')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Mvt 201: Cost Center + GL --}}
            <div x-show="movementType == '201'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Cost Center</label>
                    <select name="details[0][cost_center]"
                            class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">-- Select --</option>
                        @foreach($costCenters as $cc)
                        <option value="{{ $cc->cc_code }}">{{ $cc->cc_code }} – {{ $cc->cc_desc }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">GL Account</label>
                    <select name="details[0][gl_account]"
                            class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">-- Select --</option>
                        @foreach($glAccounts as $gl)
                        <option value="{{ $gl->account_number }}">{{ $gl->account_number }} – {{ $gl->account_desc }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Mvt 261: Order Number + GL --}}
            <div x-show="movementType == '261'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">Maintenance Order</label>
                    <select name="details[0][order_number]"
                            class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">-- Select --</option>
                        @foreach($maintenanceOrders as $mo)
                        <option value="{{ $mo->order_number }}">{{ $mo->order_number }} – {{ $mo->order_desc }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">GL Account</label>
                    <select name="details[0][gl_account]"
                            class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                        <option value="">-- Select --</option>
                        @foreach($glAccounts as $gl)
                        <option value="{{ $gl->account_number }}">{{ $gl->account_number }} – {{ $gl->account_desc }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Mvt 221: WBS --}}
            <div x-show="movementType == '221'">
                <label class="block text-xs font-medium mb-1" style="color: var(--epms-text-muted);">WBS Element</label>
                <select name="details[0][wbs_code]"
                        class="w-full rounded-lg border px-3.5 py-2 text-sm outline-none focus:border-primary"
                        style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                    <option value="">-- Select --</option>
                    @foreach($wbsList as $wbs)
                    <option value="{{ $wbs->wbs_code }}">{{ $wbs->wbs_code }} – {{ $wbs->wbs_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Material lines --}}
        <div class="rounded-xl border p-5 mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-sm" style="color: var(--epms-text);">Material Lines</h3>
                <button type="button" @click="addLine()"
                        class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-75"
                        style="border-color: var(--epms-border); color: var(--epms-text);">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Line
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--epms-border);">
                            <th class="px-3 py-2 text-left text-xs font-semibold">#</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">Material Code</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">Material Name</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">UOM</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="line.id">
                            <tr class="border-b" style="border-color: var(--epms-border);">
                                <td class="px-3 py-2 text-xs" style="color: var(--epms-text-muted);" x-text="index + 1"></td>
                                <td class="px-3 py-2">
                                    <input type="text" :name="`details[${index}][material_code]`" x-model="line.material_code"
                                           placeholder="Material code"
                                           class="w-full rounded border px-2 py-1 text-xs outline-none focus:border-primary"
                                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);"
                                           @blur="lookupMaterial(index)" required>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" :name="`details[${index}][material_name]`" x-model="line.material_name"
                                           placeholder="Name"
                                           class="w-full rounded border px-2 py-1 text-xs outline-none focus:border-primary"
                                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" :name="`details[${index}][qty]`" x-model="line.qty"
                                           step="0.001" min="0.001" placeholder="0.000"
                                           class="w-24 rounded border px-2 py-1 text-xs outline-none focus:border-primary"
                                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);" required>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" :name="`details[${index}][uom]`" x-model="line.uom"
                                           placeholder="EA / KG"
                                           class="w-20 rounded border px-2 py-1 text-xs outline-none focus:border-primary"
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
            <a href="{{ route('transactions.goods_issue.index') }}"
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
function giForm() {
    return {
        movementType: '{{ old("movement_type", $item?->movement_type ?? "") }}',
        lines: [],
        nextId: 0,

        init() {
            @if($item && $item->details->count())
            @foreach($item->details as $d)
            this.lines.push({ id: this.nextId++, material_code: '{{ $d->material_code }}', material_name: '{{ $d->material_name }}', qty: '{{ $d->qty }}', uom: '{{ $d->uom }}' });
            @endforeach
            @else
            this.addLine();
            @endif
        },

        addLine() {
            this.lines.push({ id: this.nextId++, material_code: '', material_name: '', qty: '', uom: '' });
        },

        removeLine(index) {
            if (this.lines.length > 1) this.lines.splice(index, 1);
        },

        async lookupMaterial(index) {
            const code = this.lines[index].material_code.trim();
            if (!code || this.lines[index].material_name) return;
            try {
                const res = await fetch(`{{ route('transactions.goods_issue.search_material') }}?term=${encodeURIComponent(code)}`);
                const data = await res.json();
                if (data.results?.length) {
                    const match = data.results.find(r => r.id === code) ?? data.results[0];
                    this.lines[index].material_name = match.name;
                    if (!this.lines[index].uom) this.lines[index].uom = match.uom;
                }
            } catch(e) {}
        }
    }
}
</script>
@endpush
