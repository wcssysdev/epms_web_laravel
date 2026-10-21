@extends('layouts.app')

@section('title', 'Preview ' . $resourceName)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $resourceName }} /</a></li>
    <li><span class="font-medium text-primary">Preview</span></li>
@endsection

@section('page-title', 'Preview ' . $resourceName)
@section('page-subtitle', 'Rendered with sample placeholder data')

@section('page-actions')
<div class="flex items-center gap-2">
    <a href="{{ route($routePrefix . '.edit', $row->id) }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        Edit
    </a>
    <a href="{{ route($routePrefix . '.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
</div>
@endsection

@section('content')
<div class="max-w-3xl">

    {{-- Preview notice --}}
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 mb-4 text-sm text-amber-800">
        This is a <strong>preview</strong>. Placeholder tokens (e.g.
        <code>{estate_code}</code>, <code>{date}</code>) are shown filled with
        <strong>sample data</strong> — the stored template is not changed.
    </div>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">

        {{-- Template identity --}}
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">
                {{ $row->template_name }}
                <span class="ml-1 text-xs font-normal" style="color: var(--epms-text-muted);">({{ $row->template_code }})</span>
            </h2>
        </div>

        <div class="p-5 space-y-5">
            {{-- Rendered subject --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--epms-text-muted);">Subject</p>
                <div class="rounded-lg border px-3.5 py-2.5 text-sm font-medium"
                     style="background: var(--epms-bg, #fff); color: var(--epms-text); border-color: var(--epms-border);">
                    {{ $renderedSubject }}
                </div>
            </div>

            {{-- Rendered body (admin-authored CKEditor HTML) --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--epms-text-muted);">Body</p>
                <div class="rounded-lg border p-4 text-sm bg-white text-gray-900"
                     style="border-color: var(--epms-border); min-height: 8rem;">
                    {{-- Body is admin-authored HTML (CKEditor). Rendered as HTML for the preview. --}}
                    {!! $renderedBody !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Sample values legend --}}
    <div class="rounded-xl border shadow-sm overflow-hidden mt-4"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-3 border-b" style="border-color: var(--epms-border);">
            <h3 class="text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Sample Placeholder Values</h3>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm" style="color: var(--epms-text);">
                @foreach($samples as $token => $value)
                    <div class="flex items-center gap-2">
                        <code class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">{{ $token }}</code>
                        <span style="color: var(--epms-text-muted);">&rarr;</span>
                        <span>{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
