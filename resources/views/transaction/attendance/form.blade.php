@extends('layouts.app')

@section('title', ($item ? 'Edit' : 'Add') . ' ' . $title)

@section('breadcrumb')
    <li><a href="{{ route($routePrefix . '.index') }}" class="text-gray-500 hover:text-primary">{{ $title }} /</a></li>
    <li><span class="font-medium text-primary">{{ $item ? 'Edit' : 'Add' }}</span></li>
@endsection

@section('page-title', ($item ? 'Edit' : 'Add') . ' ' . $title)

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
<div class="max-w-2xl">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-4 border-b" style="border-color: var(--epms-border);">
            <h2 class="text-sm font-semibold" style="color: var(--epms-text);">Attendance Details</h2>
        </div>
        <form method="POST"
              action="{{ $item ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}"
              class="p-5">
            @csrf
            @if($item) @method('PUT') @endif

            @php $ad = $item?->attendance_date ? \Illuminate\Support\Str::substr((string) $item->attendance_date, 0, 10) : now()->toDateString(); @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                <x-form.input name="attendance_date" label="Date" type="date" required
                              :value="old('attendance_date', $ad)"/>

                <x-form.select name="attendance_code" label="Attendance Type" required
                               :value="old('attendance_code', $item?->attendance_code)">
                    <option value="">— Select —</option>
                    @foreach($attendanceTypes as $t)
                        <option value="{{ $t->attendance_code }}"
                            @selected(old('attendance_code', $item?->attendance_code) === $t->attendance_code)>
                            {{ $t->attendance_code }} — {{ $t->attendance_desc }}
                        </option>
                    @endforeach
                </x-form.select>

                <x-form.select name="employee_code" label="Employee" required
                               :value="old('employee_code', $item?->employee_code)">
                    <option value="">— Select Employee —</option>
                    @foreach($employees as $e)
                        <option value="{{ $e->employee_code }}"
                            @selected(old('employee_code', $item?->employee_code) === $e->employee_code)>
                            {{ $e->employee_code }} — {{ $e->employee_name }}
                        </option>
                    @endforeach
                </x-form.select>

                <x-form.select name="mandor_employee_code" label="Mandor"
                               :value="old('mandor_employee_code', $item?->mandor_employee_code)">
                    <option value="">— None —</option>
                    @foreach($employees as $e)
                        <option value="{{ $e->employee_code }}"
                            @selected(old('mandor_employee_code', $item?->mandor_employee_code) === $e->employee_code)>
                            {{ $e->employee_code }} — {{ $e->employee_name }}
                        </option>
                    @endforeach
                </x-form.select>

                <x-form.input name="gang_allotment_code" label="Gang Code"
                              :value="old('gang_allotment_code', $item?->gang_allotment_code)"/>
                <x-form.input name="work_status" label="Work Status" type="number"
                              :value="old('work_status', $item?->work_status)"/>
            </div>

            <x-form.input name="remark" label="Remark"
                          :value="old('remark', $item?->remark)"/>

            <div class="flex gap-3 pt-4 mt-2 border-t" style="border-color: var(--epms-border);">
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
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
