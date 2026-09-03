@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="fertilizer_code" label="Code"        required :value="old('fertilizer_code', $item?->fertilizer_code)"/>
    <x-form.input name="fertilizer_desc" label="Description" required :value="old('fertilizer_desc', $item?->fertilizer_desc)"/>
@endsection
