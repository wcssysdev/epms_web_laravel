@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="soil_code"    label="Code"    required :value="old('soil_code', $item?->soil_code)"/>
    <x-form.input name="soil_texture" label="Texture" required :value="old('soil_texture', $item?->soil_texture)"/>
@endsection
