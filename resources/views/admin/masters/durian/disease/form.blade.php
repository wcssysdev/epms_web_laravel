@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="disease_code" label="Code"        required :value="old('disease_code', $item?->disease_code)"/>
    <x-form.input name="disease_desc" label="Description" required :value="old('disease_desc', $item?->disease_desc)"/>
@endsection
