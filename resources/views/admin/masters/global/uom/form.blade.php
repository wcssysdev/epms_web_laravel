@extends('admin.masters.global._shared.form')

@section('form-fields')
    <x-form.input name="uom_code" label="UOM Code" required
                  :value="old('uom_code', $item?->uom_code)" placeholder="e.g. KG, TON, UNIT"/>
    <x-form.input name="uom_desc" label="Description" required
                  :value="old('uom_desc', $item?->uom_desc)" placeholder="e.g. Kilogram, Metric Ton"/>
@endsection
