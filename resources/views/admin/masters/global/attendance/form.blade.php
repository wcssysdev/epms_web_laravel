@extends('admin.masters.global._shared.form')

@section('form-fields')
    <x-form.input name="attendance_code" label="Attendance Code" required
                  :value="old('attendance_code', $item?->attendance_code)" placeholder="e.g. P, A, MC"/>
    <x-form.input name="attendance_desc" label="Description" required
                  :value="old('attendance_desc', $item?->attendance_desc)" placeholder="e.g. Present, Absent"/>
@endsection
