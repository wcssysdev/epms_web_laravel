@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="field_staff_gang_code"     label="Gang Code"     required :value="old('field_staff_gang_code', $item?->field_staff_gang_code)"     placeholder="e.g. GANG001"/>
    <x-form.input name="field_staff_employee_code" label="Employee Code" required :value="old('field_staff_employee_code', $item?->field_staff_employee_code)" placeholder="Field staff employee code"/>
    <x-form.input name="field_staff_employee_name" label="Employee Name" required :value="old('field_staff_employee_name', $item?->field_staff_employee_name)" placeholder="Field staff name"/>
@endsection
