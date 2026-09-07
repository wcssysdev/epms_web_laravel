@extends('layouts.app')

@section('title', 'Preview ' . $resourceName)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $resourceName }} /</a></li>
    <li><a href="{{ route($routePrefix . '.upload') }}" class="text-gray-500 hover:text-primary">Import /</a></li>
    <li><span class="font-medium text-primary">Preview</span></li>
@endsection

@section('page-title', 'Preview: ' . $resourceName . ' Import')
@section('page-subtitle', 'Rows are grouped by ' . $groupKey . ' — review before saving')

@section('content')

<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="rounded-xl border p-4 text-center" style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <p class="text-2xl font-bold text-green-600">{{ $validCount }}</p>
        <p class="text-xs mt-1" style="color: var(--epms-text-muted);">Valid Records (headers)</p>
    </div>
    <div class="rounded-xl border p-4 text-center" style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <p class="text-2xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $errorCount }}</p>
        <p class="text-xs mt-1" style="color: var(--epms-text-muted);">Issues</p>
    </div>
    <div class="rounded-xl border p-4 text-center" style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <p class="text-2xl font-bold" style="color: var(--epms-text);">{{ count($preview) }}</p>
        <p class="text-xs mt-1" style="color: var(--epms-text-muted);">Shown</p>
    </div>
</div>

@if(!empty($errors))
<div class="rounded-xl border border-red-200 bg-red-50 p-4 mb-5">
    <p class="font-semibold text-red-700 text-sm mb-2">⚠ Issues ({{ $errorCount }}):</p>
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors as $err)
        <li class="text-xs text-red-600">{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="rounded-xl border shadow-sm overflow-hidden mb-5"
     style="background: var(--epms-header-bg); border-color: var(--epms-border);">
    <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
        <h2 class="font-semibold text-sm" style="color: var(--epms-text);">Grouped Preview (up to 100)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs" style="color: var(--epms-text);">
            <thead>
                <tr class="border-b" style="border-color: var(--epms-border); background: var(--epms-bg);">
                    <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ str_replace('_',' ',$groupKey) }}</th>
                    <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Division</th>
                    <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Detail Lines</th>
                    <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($preview as $row)
                <tr class="border-b" style="border-color: var(--epms-border);">
                    <td class="px-3 py-2 font-medium">{{ $row['data'][$groupKey] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $row['data']['division_code'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $row['data']['lines'] ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $row['data']['total_bunches'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="flex items-center gap-3">
    @if($validCount > 0)
    <form method="POST" action="{{ route($routePrefix . '.save-uploaded-data') }}">
        @csrf
        <button type="submit"
                class="flex items-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Save {{ $validCount }} Records
        </button>
    </form>
    @endif
    <a href="{{ route($routePrefix . '.upload') }}"
       class="rounded-lg border px-6 py-2.5 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">Upload Different File</a>
    <a href="{{ route($routePrefix . '.cancel') }}"
       class="rounded-lg border px-6 py-2.5 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
</div>

@endsection
