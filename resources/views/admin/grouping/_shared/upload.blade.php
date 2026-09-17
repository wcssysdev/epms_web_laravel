@extends('layouts.app')

@section('title', 'Upload ' . $resourceName)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $resourceName }} /</a></li>
    <li><span class="font-medium text-primary">Upload CSV</span></li>
@endsection

@section('page-title', 'Upload ' . $resourceName)
@section('page-subtitle', 'Upload CSV file to import ' . strtolower($resourceName) . ' data')

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
<div class="max-w-xl">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">

        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="font-semibold text-sm" style="color: var(--epms-text);">Upload CSV File</h2>
        </div>

        <div class="p-5">
            {{-- CSV Template Download --}}
            <div class="flex items-center gap-3 rounded-lg border p-4 mb-5"
                 style="border-color: var(--epms-border); background: var(--epms-bg);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-medium" style="color: var(--epms-text);">Need the CSV template?</p>
                    <p class="text-xs mt-0.5" style="color: var(--epms-text-muted);">Download the template with the correct column headers.</p>
                </div>
                <a href="{{ route($routePrefix . '.generate-csv') }}"
                   class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                   style="border-color: var(--epms-border); color: var(--epms-text);">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                </a>
            </div>

            {{-- Upload Form --}}
            <form method="POST" action="{{ route($routePrefix . '.preview') }}"
                  enctype="multipart/form-data"
                  x-data="{ fileName: '', dragging: false }">
                @csrf

                {{-- Drop Zone --}}
                <div class="relative mb-5">
                    <label for="csv_file"
                           class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed p-8 cursor-pointer transition"
                           :class="dragging ? 'border-primary bg-primary/5' : 'border-gray-300'"
                           style="border-color: var(--epms-border);"
                           @dragover.prevent="dragging = true"
                           @dragleave.prevent="dragging = false"
                           @drop.prevent="dragging = false; fileName = $event.dataTransfer.files[0]?.name; $refs.csvInput.files = $event.dataTransfer.files">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" :class="dragging ? 'text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: var(--epms-text-muted);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <div class="text-center">
                            <p class="text-sm font-medium" style="color: var(--epms-text);" x-text="fileName || 'Drop CSV file here or click to browse'"></p>
                            <p class="text-xs mt-1" style="color: var(--epms-text-muted);">Supported: .csv, .txt — Max 10MB</p>
                        </div>
                    </label>
                    <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt"
                           x-ref="csvInput" class="sr-only"
                           @change="fileName = $event.target.files[0]?.name">
                </div>
                @error('csv_file')<p class="text-xs text-red-500 -mt-3 mb-4">{{ $message }}</p>@enderror

                <div class="flex gap-3">
                    <button type="submit"
                            class="flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
                            x-bind:disabled="!fileName">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview Data
                    </button>
                    <a href="{{ route($routePrefix . '.index') }}"
                       class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                       style="border-color: var(--epms-border); color: var(--epms-text);">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
