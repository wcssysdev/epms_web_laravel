@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="gang_code"          label="Gang Code"      required :value="old('gang_code', $item?->gang_code)"                   placeholder="e.g. GANG001"/>
    <x-form.input name="gang_employee_code" label="Employee Code"  required :value="old('gang_employee_code', $item?->gang_employee_code)" placeholder="Employee code"/>
    <x-form.input name="gang_employee_name" label="Employee Name"  required :value="old('gang_employee_name', $item?->gang_employee_name)" placeholder="Employee full name"/>
@endsection
