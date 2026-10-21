@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $resourceName)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $resourceName }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($item ? 'Edit' : 'Add') . ' ' . $resourceName)

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
<div class="max-w-3xl">
    <div class="rounded-xl border shadow-sm"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">{{ $resourceName }} Information</h2>
        </div>
        <form method="POST"
              action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.save') }}"
              class="p-5">
            @csrf
            @if($item) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="template_code" label="Template Code" required
                              :value="old('template_code', $item?->template_code)"
                              placeholder="e.g. ACCIDENT_NOTIFY"
                              hint="Unique across all templates."/>

                <x-form.input name="template_name" label="Template Name" required
                              :value="old('template_name', $item?->template_name)"
                              placeholder="e.g. Accident Notification"/>
            </div>

            <x-form.input name="template_subject" label="Subject" required
                          :value="old('template_subject', $item?->template_subject)"
                          placeholder="e.g. [HSE] Accident reported at {estate_code}"/>

            {{-- R8.2 — CKEditor HTML editor bound to the template_body textarea. --}}
            <div class="form-control mb-4">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);" for="template_body">
                    Body (HTML)<span class="text-red-500 ml-0.5">*</span>
                </label>
                <textarea name="template_body" id="template_body" rows="12"
                          class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition
                                 focus:border-primary focus:ring-1 focus:ring-primary
                                 {{ $errors->has('template_body') ? 'border-red-400' : '' }}"
                          style="background: var(--epms-header-bg); color: var(--epms-text); border-color: {{ $errors->has('template_body') ? '' : 'var(--epms-border)' }};">{{ old('template_body', $item?->template_body) }}</textarea>
                @error('template_body')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs" style="color: var(--epms-text-muted);">
                    Placeholder tokens (e.g. <code>{estate_code}</code>, <code>{date}</code>) are substituted on preview.
                </p>
            </div>

            {{-- Active toggle --}}
            <div class="form-control mb-4">
                <label class="inline-flex items-center gap-2 text-sm font-medium" style="color: var(--epms-text);">
                    <input type="hidden" name="template_is_active" value="0">
                    <input type="checkbox" name="template_is_active" value="1"
                           {{ (int) old('template_is_active', $item?->template_is_active ?? 1) === 1 ? 'checked' : '' }}
                           class="rounded border-gray-300 text-primary focus:ring-primary">
                    Active
                </label>
            </div>

            <div class="flex gap-3 pt-4 border-t mt-4" style="border-color: var(--epms-border);">
                <button type="submit"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
                    {{ $item ? 'Update' : 'Save' }}
                </button>
                <a href="{{ route($routePrefix.'.index') }}"
                   class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                   style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
{{--
    CKEditor is NOT bundled in this Laravel app (the CI3 apps ship CKEditor 4 under
    template_assets/global/plugins/ckeditor, but epms_laravel has no local copy).
    Per design.md ("CKEditor (CDN, as in CI3)"), it is loaded from the official CDN
    (CKEditor 4 classic, matching the CI3 editor version) and attached to the
    template_body textarea. CKEDITOR.updateElement() is called on submit so the
    edited HTML is written back into the textarea before the form posts.
--}}
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.CKEDITOR) {
            CKEDITOR.replace('template_body');
            // Ensure the CKEditor HTML is synced to the underlying textarea on submit.
            const form = document.getElementById('template_body').closest('form');
            if (form) {
                form.addEventListener('submit', function () {
                    for (const name in CKEDITOR.instances) {
                        CKEDITOR.instances[name].updateElement();
                    }
                });
            }
        }
    });
</script>
@endpush
