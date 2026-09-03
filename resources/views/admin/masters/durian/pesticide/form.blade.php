@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="pesticide_code" label="Code"        required :value="old('pesticide_code', $item?->pesticide_code)"/>
    <x-form.input name="pesticide_desc" label="Description" required :value="old('pesticide_desc', $item?->pesticide_desc)"/>
@endsection
