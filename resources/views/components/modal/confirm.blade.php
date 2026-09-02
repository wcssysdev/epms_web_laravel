{{-- Usage: <x-modal.confirm id="myModal" title="Confirm" /> --}}
@props(['id', 'title' => 'Confirm Action'])

<div id="{{ $id }}"
     class="fixed inset-0 z-50 hidden items-center justify-center"
     x-data="{ show: false }"
     x-show="show"
     x-on:open-modal-{{ $id }}.window="show = true"
     x-on:close-modal-{{ $id }}.window="show = false">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="show = false"></div>

    {{-- Modal --}}
    <div class="relative w-full max-w-md rounded-xl border p-6 shadow-xl z-10"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <h3 class="text-lg font-bold mb-2" style="color: var(--epms-text);">{{ $title }}</h3>
        <div style="color: var(--epms-text-muted);">{{ $slot }}</div>
        <div class="flex gap-3 mt-5 justify-end">
            <button @click="show = false"
                    class="px-4 py-2 rounded-lg text-sm font-medium border transition hover:opacity-80"
                    style="border-color: var(--epms-border); color: var(--epms-text);">
                Cancel
            </button>
            <button id="{{ $id }}-confirm"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white transition hover:opacity-90">
                Confirm
            </button>
        </div>
    </div>
</div>
