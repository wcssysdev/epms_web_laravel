@extends('layouts.app')

@section('title', 'Delete Pictures')

@section('breadcrumb')
    <li><span class="font-medium text-primary">Delete Pictures</span></li>
@endsection

@section('page-title', 'Delete Pictures')
@section('page-subtitle', 'Clear picture references from transaction records')

@section('content')
<div x-data="deletePictures()">

    <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 mb-5 text-sm text-amber-700">
        <p class="font-semibold">⚠ Warning</p>
        <p class="mt-1">This removes photo references from the database only. Actual image files are NOT deleted. This action cannot be undone.</p>
    </div>

    {{-- Result --}}
    <div x-show="resultMessage" x-transition
         class="rounded-xl border px-5 py-3 mb-4 text-sm"
         :class="resultSuccess ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'"
         x-text="resultMessage">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($stats as $item)
        <div class="rounded-xl border shadow-sm p-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);"
             x-data="{ dateFrom: '', dateTo: '', count: {{ $item['total'] }}, counting: false }">

            <div class="flex items-center justify-between mb-3">
                <p class="font-semibold text-sm" style="color: var(--epms-text);">{{ $item['label'] }}</p>
                <span class="text-xs px-2 py-1 rounded-full bg-primary/10 text-primary font-medium"
                      x-text="count + ' photos'">{{ $item['total'] }} photos</span>
            </div>

            {{-- Date filter --}}
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div>
                    <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">From</label>
                    <input type="date" x-model="dateFrom"
                           class="w-full rounded-lg border px-2.5 py-2 text-xs outline-none"
                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color: var(--epms-text-muted);">To</label>
                    <input type="date" x-model="dateTo"
                           class="w-full rounded-lg border px-2.5 py-2 text-xs outline-none"
                           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);">
                </div>
            </div>

            <div class="flex gap-2">
                <button @click="checkCount('{{ $item['table'] }}')"
                        :disabled="counting"
                        class="flex-1 rounded-lg border px-3 py-2 text-xs font-medium transition hover:border-primary hover:text-primary"
                        style="border-color: var(--epms-border); color: var(--epms-text);">
                    <span x-text="counting ? 'Counting...' : 'Count'">Count</span>
                </button>
                <button @click="confirmDelete('{{ $item['table'] }}', '{{ $item['label'] }}')"
                        :disabled="count === 0"
                        class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Clear Photos
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Confirm Modal --}}
    <div x-show="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center" x-transition>
        <div class="absolute inset-0 bg-black/50" @click="showConfirm = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <h3 class="text-base font-bold mb-2" style="color: var(--epms-text);">Confirm Delete Pictures</h3>
            <p class="text-sm mb-5" style="color: var(--epms-text-muted);">
                Clear all photo references from <strong x-text="confirmLabel"></strong>? This cannot be undone.
            </p>
            <div class="flex gap-3 justify-end">
                <button @click="showConfirm = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</button>
                <button @click="doDelete()" :disabled="deleting"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition">
                    <span x-show="!deleting">Delete</span>
                    <span x-show="deleting" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function deletePictures() {
    return {
        resultMessage:  '',
        resultSuccess:  false,
        showConfirm:    false,
        confirmTable:   '',
        confirmLabel:   '',
        confirmFrom:    '',
        confirmTo:      '',
        deleting:       false,

        async checkCount(table) {
            // Find the component for this table
            const comps = document.querySelectorAll('[x-data*="dateFrom"]');
            // Use fetch
            try {
                const res  = await fetch('{{ route("admin.delete-pictures.count") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ table })
                });
                const json = await res.json();
                if (json.success) this.resultMessage = `${table}: ${json.data.count} photos found`;
            } catch (e) { this.resultMessage = 'Error: ' + e.message; this.resultSuccess = false; }
        },

        confirmDelete(table, label) {
            this.confirmTable = table;
            this.confirmLabel = label;
            this.showConfirm  = true;
        },

        async doDelete() {
            this.deleting = true;
            try {
                const res  = await fetch('{{ route("admin.delete-pictures.delete") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ table: this.confirmTable, date_from: this.confirmFrom, date_to: this.confirmTo })
                });
                const json = await res.json();
                this.resultSuccess = json.success;
                this.resultMessage = json.message;
                this.showConfirm   = false;
                if (json.success) setTimeout(() => location.reload(), 1500);
            } catch (e) {
                this.resultSuccess = false;
                this.resultMessage = 'Error: ' + e.message;
            } finally {
                this.deleting = false;
            }
        }
    }
}
</script>
@endpush
