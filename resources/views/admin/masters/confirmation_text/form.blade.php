@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="ctext_code" label="Code" required :value="old('ctext_code', $item?->ctext_code)" placeholder="e.g. CT001"/>
    <x-form.input name="ctext_text" label="Text" required :value="old('ctext_text', $item?->ctext_text)" placeholder="Confirmation text"/>
    <x-form.input name="ctext_desc" label="Description" :value="old('ctext_desc', $item?->ctext_desc)" placeholder="Optional description"/>
@endsection
