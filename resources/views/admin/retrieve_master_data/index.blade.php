@extends('layouts.app')

@section('title', 'Retrieve Master Data')

@section('breadcrumb')
    <li><span class="font-medium text-primary">Retrieve Master Data</span></li>
@endsection

@section('page-title', 'Retrieve Master Data')
@section('page-subtitle', 'Trigger SAP sync for master data tables')

@section('page-actions')
    <button x-data @click="$dispatch('sync-all')"
            class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Sync All
    </button>
@endsection

@section('content')
<div x-data="retrieveMasterData()" @sync-all.window="syncAll()">

    {{-- Result banner --}}
    <div x-show="resultMessage" x-transition
         class="rounded-xl border px-5 py-3 mb-4 text-sm"
         :class="resultSuccess ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'"
         x-text="resultMessage">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($status as $key => $item)
        <div class="rounded-xl border shadow-sm p-5"
             style="background: var(--epms-header-bg); border-color: var(--epms-border);">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">{{ $item['icon'] }}</span>
                    <div>
                        <p class="font-semibold text-sm" style="color: var(--epms-text);">{{ $item['label'] }}</p>
                        <p class="text-xs" style="color: var(--epms-text-muted);">{{ $item['table'] }}</p>
                    </div>
                </div>
                <span class="text-xl font-bold text-primary">{{ number_format($item['total']) }}</span>
            </div>

            @if($item['last_updated'])
            <p class="text-xs mb-3" style="color: var(--epms-text-muted);">
                Last sync: {{ \Carbon\Carbon::parse($item['last_updated'])->format('d/m/Y H:i') }}
            </p>
            @else
            <p class="text-xs mb-3 text-amber-500">Never synced</p>
            @endif

            <button @click="syncOne('{{ $key }}', '{{ $item['label'] }}')"
                    :disabled="syncing === '{{ $key }}'"
                    class="w-full flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-xs font-medium transition hover:border-primary hover:text-primary"
                    style="border-color: var(--epms-border); color: var(--epms-text);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5"
                     :class="{ 'animate-spin': syncing === '{{ $key }}' }"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="syncing === '{{ $key }}' ? 'Syncing...' : 'Sync {{ $item['label'] }}'"></span>
            </button>
        </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
function retrieveMasterData() {
    return {
        syncing:        null,
        resultMessage:  '',
        resultSuccess:  false,

        async syncOne(key, label) {
            this.syncing = key;
            this.resultMessage = '';
            try {
                const res  = await fetch('{{ route("admin.retrieve-master.sync") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ table_key: key })
                });
                const json = await res.json();
                this.resultSuccess = json.success;
                this.resultMessage = json.message;
            } catch (e) {
                this.resultSuccess = false;
                this.resultMessage = 'Request failed: ' + e.message;
            } finally {
                this.syncing = null;
            }
        },

        async syncAll() {
            this.syncing = '__all__';
            this.resultMessage = '';
            try {
                const res  = await fetch('{{ route("admin.retrieve-master.sync-all") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const json = await res.json();
                this.resultSuccess = json.success;
                this.resultMessage = json.message;
            } catch (e) {
                this.resultSuccess = false;
                this.resultMessage = 'Request failed: ' + e.message;
            } finally {
                this.syncing = null;
            }
        }
    }
}
</script>
@endpush
