@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="mandor_employee_code"      label="Mandor Code"        required :value="old('mandor_employee_code', $item?->mandor_employee_code)"           placeholder="Mandor employee code"/>
    <x-form.input name="mandor_employee_name"      label="Mandor Name"        required :value="old('mandor_employee_name', $item?->mandor_employee_name)"           placeholder="Mandor full name"/>
    <x-form.input name="field_staff_employee_code" label="Field Staff Code"   required :value="old('field_staff_employee_code', $item?->field_staff_employee_code)" placeholder="Field staff employee code"/>
    <x-form.input name="field_staff_employee_name" label="Field Staff Name"   required :value="old('field_staff_employee_name', $item?->field_staff_employee_name)" placeholder="Field staff name"/>
@endsection
