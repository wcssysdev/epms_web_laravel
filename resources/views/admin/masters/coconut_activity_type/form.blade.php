@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="coconut_activity_type_code" label="Code" required :value="old('coconut_activity_type_code', $item?->coconut_activity_type_code)" placeholder="e.g. CAT01"/>
    <x-form.input name="coconut_activity_type_desc" label="Description" required :value="old('coconut_activity_type_desc', $item?->coconut_activity_type_desc)" placeholder="Activity type description"/>
@endsection
