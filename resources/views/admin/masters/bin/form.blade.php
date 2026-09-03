@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="bin_code" label="BIN Code" required :value="old('bin_code', $item?->bin_code)" placeholder="e.g. BIN001"/>
@endsection
