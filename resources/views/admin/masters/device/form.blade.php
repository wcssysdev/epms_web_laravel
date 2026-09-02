@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' Device')

@section('breadcrumb')
    <li><a href="{{ route('masters.device.index') }}" class="text-gray-500 hover:text-primary">Device /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($item ? 'Edit' : 'Add') . ' Device')
@section('page-subtitle', 'Manage mobile device registration')

@section('page-actions')
    <a href="{{ route('masters.device.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-80"
       style="border-color: var(--epms-border); color: var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
<div class="max-w-md">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Device Information</h2>
        </div>
        <form method="POST"
              action="{{ $item ? route('masters.device.update', $item->id) : route('masters.device.save') }}"
              class="p-5">
            @csrf
            @if($item) @method('PUT') @endif

            <x-form.input name="device_code" label="Device Code" required
                          :value="$item?->device_code" placeholder="e.g. MOB001"/>
            <x-form.input name="estate_code" label="Estate Code" required
                          :value="$item?->estate_code" placeholder="e.g. TST"/>
            <x-form.input name="device_imei" label="Device IMEI"
                          :value="$item?->device_imei" placeholder="15-digit IMEI number"/>

            <div class="flex gap-3 pt-4 border-t" style="border-color: var(--epms-border);">
                <button type="submit"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
                    {{ $item ? 'Update' : 'Save' }}
                </button>
                <a href="{{ route('masters.device.index') }}"
                   class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                   style="border-color: var(--epms-border); color: var(--epms-text);">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
