@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="crop_type"       label="Crop Type"     :value="old('crop_type', $item?->crop_type)" placeholder="e.g. DURIAN"/>
    <x-form.input name="type_of_variety" label="Variety Type"  :value="old('type_of_variety', $item?->type_of_variety)"/>
    <x-form.input name="grading_code"    label="Grading Code"  required :value="old('grading_code', $item?->grading_code)"/>
    <x-form.input name="grading_weight"  label="Grading Weight" :value="old('grading_weight', $item?->grading_weight)"/>
@endsection
