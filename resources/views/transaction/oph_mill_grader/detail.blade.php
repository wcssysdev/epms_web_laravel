@extends('layouts.app')

@section('title', $title)

@section('breadcrumb')
    <li><a href="{{ route('transactions.oph_mill_grader.index') }}" class="text-gray-500 hover:text-primary">OPH (Mill Grader) /</a></li>
    <li><span class="font-medium text-primary">Detail</span></li>
@endsection

@section('page-title', $title)

@section('page-actions')
    <a href="{{ route('transactions.oph_mill_grader.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Record {{ $item->id }}</h2>
        </div>
        <dl class="divide-y" style="border-color: var(--epms-border);">
            @php
                $fields = [
                    'Division' => $item->division_code, 'Block' => $item->block_code, 'TPH' => $item->tph_code,
                    'Grader Code' => $item->grader_employee_code, 'Grader' => $item->grader_employee_name,
                    'SAP Status' => $item->integration_status, 'Approved' => $item->is_approved ? 'Yes' : 'No',
                    'Remark' => $item->remark,
                ];
            @endphp
            @foreach($fields as $label => $value)
                <div class="flex gap-4 px-5 py-3">
                    <dt class="w-40 text-sm" style="color: var(--epms-text-muted);">{{ $label }}</dt>
                    <dd class="text-sm font-medium" style="color: var(--epms-text);">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
@endsection
