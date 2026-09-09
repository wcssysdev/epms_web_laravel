@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $title)
@section('breadcrumb')
    <li><a href="{{ route($routePrefix.'.index') }}" class="text-gray-500 hover:text-primary">{{ $title }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection
@section('page-title', ($item ? 'Edit' : 'Add') . ' ' . $title)
@section('page-actions')
    <a href="{{ route($routePrefix.'.index') }}"
       class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:opacity-80"
       style="border-color:var(--epms-border);color:var(--epms-text);">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>Back
    </a>
@endsection

@section('content')
<div class="max-w-2xl">
<form method="POST"
      action="{{ $item ? route($routePrefix.'.update',$item->id) : route($routePrefix.'.store') }}">
    @csrf
    @if($item) @method('PUT') @endif

    <div class="rounded-xl border shadow-sm overflow-hidden mb-5"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color:var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color:var(--epms-text);">Overtime Details</h2>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-5">

            <x-form.input name="overtime_date" label="Date" type="date" required
                          :value="old('overtime_date', $item?->overtime_date?->format('Y-m-d'))"/>

            <x-form.select name="division_code" label="Division" required
                           :value="old('division_code', $item?->division_code)">
                <option value="">— Select Division —</option>
                @foreach($divisions as $d)
                    <option value="{{ $d->division_code }}" @selected(old('division_code',$item?->division_code)===$d->division_code)>
                        {{ $d->division_code }} — {{ $d->division_name }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.select name="employee_code" label="Employee" required
                           :value="old('employee_code', $item?->employee_code)">
                <option value="">— Select Employee —</option>
                @foreach($employees as $e)
                    <option value="{{ $e->employee_code }}" @selected(old('employee_code',$item?->employee_code)===$e->employee_code)>
                        {{ $e->employee_code }} — {{ $e->employee_name }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.select name="activity_code" label="Activity" required
                           :value="old('activity_code', $item?->activity_code)">
                <option value="">— Select Activity —</option>
                @foreach($activities as $a)
                    <option value="{{ $a->activity_code }}" @selected(old('activity_code',$item?->activity_code)===$a->activity_code)>
                        {{ $a->activity_code }} — {{ $a->activity_name }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.input name="start_time" label="Start Time (HH:MM)" type="time" required
                          :value="old('start_time', $item?->start_time)"/>

            <x-form.input name="end_time" label="End Time (HH:MM)" type="time" required
                          :value="old('end_time', $item?->end_time)"/>

            <x-form.input name="duration_hours" label="Duration (hours)" type="number" step="0.1" min="0.1" required
                          :value="old('duration_hours', $item?->duration_hours)"/>

            <x-form.select name="block_code" label="Block (optional)"
                           :value="old('block_code', $item?->block_code)">
                <option value="">— None —</option>
                @foreach($blocks as $b)
                    <option value="{{ $b->block_code }}" @selected(old('block_code',$item?->block_code)===$b->block_code)>
                        {{ $b->block_code }} — {{ $b->block_name }}
                    </option>
                @endforeach
            </x-form.select>

            <x-form.input name="order_number" label="Order Number"
                          :value="old('order_number', $item?->order_number)"/>

            <x-form.input name="cost_center" label="Cost Center"
                          :value="old('cost_center', $item?->cost_center)"/>

            <div class="md:col-span-2">
                <x-form.input name="remark" label="Remark"
                              :value="old('remark', $item?->remark)"/>
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit"
                class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
            {{ $item ? 'Update' : 'Save' }}
        </button>
        <a href="{{ route($routePrefix.'.index') }}"
           class="rounded-lg border px-5 py-2.5 text-sm font-medium hover:opacity-80 transition"
           style="border-color:var(--epms-border);color:var(--epms-text);">Cancel</a>
    </div>
</form>
</div>
@endsection
