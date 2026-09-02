@extends('admin.masters.global._shared.form')

@section('form-fields')
    <x-form.input name="mvt_type_code"     label="Movement Type Code" required :value="old('mvt_type_code', $item?->mvt_type_code)"/>
    <x-form.input name="mvt_type_doc_type" label="Document Type"      required :value="old('mvt_type_doc_type', $item?->mvt_type_doc_type)"/>
    <x-form.input name="mvt_type_desc"     label="Description"        required :value="old('mvt_type_desc', $item?->mvt_type_desc)"/>
@endsection
