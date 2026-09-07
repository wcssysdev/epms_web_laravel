@extends('layouts.app')

@section('title', 'Grade ' . $title)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $title }} /</a></li>
    <li><span class="font-medium text-primary">Grade Chit</span></li>
@endsection

@section('page-title', 'Grade ' . $title)
@section('page-subtitle', 'Chit ' . $item->id)

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
<div class="max-w-4xl" x-data="gradingForm()">

    {{-- Chit summary (read-only) --}}
    <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Harvesting Chit</h2>
        </div>
        <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><span class="block text-xs" style="color: var(--epms-text-muted);">Division</span>{{ $item->division_code }}</div>
            <div><span class="block text-xs" style="color: var(--epms-text-muted);">Block</span>{{ $item->block_code }}</div>
            <div><span class="block text-xs" style="color: var(--epms-text-muted);">TPH</span>{{ $item->tph_code }}</div>
            <div><span class="block text-xs" style="color: var(--epms-text-muted);">Nuts Total</span><span class="font-bold text-primary">{{ $item->nuts_total }}</span></div>
        </div>
        @if($persons->isNotEmpty())
        <div class="px-5 pb-4">
            <span class="block text-xs mb-1" style="color: var(--epms-text-muted);">Harvesters</span>
            <div class="flex flex-wrap gap-2">
                @foreach($persons as $p)
                    <span class="rounded-md border px-2 py-1 text-xs" style="border-color: var(--epms-border); color: var(--epms-text);">
                        {{ $p->employee_name ?: $p->employee_code }}
                    </span>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <form method="POST" action="{{ route($routePrefix.'.update', $item->id) }}">
        @csrf
        @method('PUT')

        {{-- Grading material lines --}}
        <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--epms-border);">
                <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Grading Materials</h2>
                <div class="flex items-center gap-4 text-sm">
                    <span style="color: var(--epms-text-muted);">Total Qty: <span class="font-bold text-primary" x-text="totalQty"></span></span>
                    <button type="button" @click="addRow()"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                            style="border-color: var(--epms-border); color: var(--epms-text);">+ Add Material</button>
                </div>
            </div>
            <div class="p-5">
                <table class="w-full text-sm" style="color: var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color: var(--epms-border);">
                            <th class="px-2 py-2 text-left text-xs uppercase" style="color: var(--epms-text-muted);">Material</th>
                            <th class="px-2 py-2 text-left text-xs uppercase" style="color: var(--epms-text-muted);">Customer Nut Qty</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(g, i) in rows" :key="i">
                            <tr class="border-b" style="border-color: var(--epms-border);">
                                <td class="px-2 py-1.5">
                                    <select :name="`gradings[${i}][material_code]`" x-model="g.material_code"
                                            class="w-full rounded border px-2 py-1 text-sm"
                                            style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                                        <option value="">— Select Material —</option>
                                        @foreach($materials as $m)
                                            <option value="{{ $m->material_code }}">{{ $m->material_code }} — {{ $m->material_desc }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="number" step="0.01" min="0" :name="`gradings[${i}][customer_nut_qty]`"
                                           x-model="g.customer_nut_qty" @input="recalc()"
                                           class="w-32 rounded border px-2 py-1 text-sm"
                                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                                </td>
                                <td class="px-2 py-1.5 text-right">
                                    <button type="button" @click="removeRow(i)"
                                            class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-600 hover:bg-red-100">Remove</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="rows.length === 0">
                            <td colspan="3" class="px-2 py-3 text-center text-xs" style="color: var(--epms-text-muted);">
                                No grading lines. Click "+ Add Material".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
                Save Grading
            </button>
            <a href="{{ route($routePrefix.'.index') }}"
               class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
               style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function gradingForm() {
    return {
        rows: @js($gradings->map(fn($g) => [
            'material_code' => $g->material_code, 'customer_nut_qty' => $g->customer_nut_qty,
        ])->values()),
        totalQty: 0,
        init() { this.recalc(); },
        recalc() {
            this.totalQty = this.rows.reduce((s, g) => s + (parseFloat(g.customer_nut_qty || 0) || 0), 0);
        },
        addRow() { this.rows.push({ material_code: '', customer_nut_qty: 0 }); },
        removeRow(i) { this.rows.splice(i, 1); this.recalc(); },
    }
}
</script>
@endpush
