@extends('admin.masters.global._shared.form')

@section('form-fields')
    <x-form.input name="mhm_indicator"    label="Indicator (1 char)"  required :value="old('mhm_indicator', $item?->mhm_indicator)" placeholder="e.g. M"/>
    <x-form.input name="mhm_abbreviation" label="Abbreviation"        required :value="old('mhm_abbreviation', $item?->mhm_abbreviation)" placeholder="e.g. Manual"/>
    <x-form.input name="mhm_description"  label="Description"         required :value="old('mhm_description', $item?->mhm_description)"/>
    <x-form.select name="mhm_order_number_flag" label="Order Number Flag">
        <option value="N" {{ old('mhm_order_number_flag', $item?->mhm_order_number_flag ?? 'N') == 'N' ? 'selected' : '' }}>No</option>
        <option value="Y" {{ old('mhm_order_number_flag', $item?->mhm_order_number_flag) == 'Y' ? 'selected' : '' }}>Yes</option>
    </x-form.select>
@endsection
