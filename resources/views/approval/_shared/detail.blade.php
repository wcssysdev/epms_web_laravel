{{--
  Shared read-only detail. Expected variables:
    $title, $backRoute, $subtitle
    $info    array of ['label' => 'value']
    $lineTitle  string (e.g. 'Harvesters', 'Materials') or null to hide table
    $lineHeaders array of header labels
    $lines   collection
    $lineRow closure($row) => array of cell values
--}}
@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><span class="text-gray-500">Approval /</span></li>
    <li><a href="{{ route($backRoute) }}" class="text-gray-500 hover:text-primary">Back /</a></li>
    <li><span class="font-medium text-primary">Detail</span></li>
@endsection

@section('page-title', $title)
@section('page-subtitle', $subtitle ?? '')

@section('page-actions')
    <a href="{{ route($backRoute) }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Record Information</h2>
        </div>
        <dl class="p-5 grid gap-x-8 gap-y-4 md:grid-cols-2 text-sm" style="color: var(--epms-text);">
            @foreach($info as $label => $value)
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--epms-text-muted);">{{ $label }}</dt>
                <dd class="mt-0.5 font-medium">{{ $value }}</dd>
            </div>
            @endforeach
        </dl>
    </div>

    @if(!empty($lineTitle))
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">{{ $lineTitle }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color: var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        @foreach($lineHeaders as $h)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color: var(--epms-text-muted);">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        @foreach($lineRow($line) as $cell)
                        <td class="px-4 py-3">{{ $cell }}</td>
                        @endforeach
                    </tr>
                    @empty
                    <tr><td colspan="{{ count($lineHeaders) }}" class="px-4 py-8 text-center text-sm" style="color: var(--epms-text-muted);">No records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
