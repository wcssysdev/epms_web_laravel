@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="assistant_manager_code" label="Asst. Manager Code" required :value="old('assistant_manager_code', $item?->assistant_manager_code)" placeholder="Assistant manager employee code"/>
    <x-form.input name="assistant_manager_name" label="Asst. Manager Name" required :value="old('assistant_manager_name', $item?->assistant_manager_name)" placeholder="Assistant manager full name"/>
    <x-form.input name="division_code"          label="Division Code"      required :value="old('division_code', $item?->division_code)"                   placeholder="e.g. D01"/>
    <x-form.input name="division_name"          label="Division Name"      required :value="old('division_name', $item?->division_name)"                   placeholder="Division name"/>
@endsection
