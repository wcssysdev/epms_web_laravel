@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="activity_code"       label="Code"       required :value="old('activity_code', $item?->activity_code)"/>
    <x-form.input name="activity_group_code" label="Group Code" :value="old('activity_group_code', $item?->activity_group_code)"/>
    <x-form.input name="activity_name"       label="Name"       required :value="old('activity_name', $item?->activity_name)"/>
@endsection
