@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="estate_code"   label="Estate Code"   required :value="old('estate_code', $item?->estate_code)" placeholder="e.g. SG"/>
    <x-form.input name="division_code" label="Division Code" required :value="old('division_code', $item?->division_code)" placeholder="e.g. D01"/>
    <x-form.input name="block_code"    label="Block Code"    required :value="old('block_code', $item?->block_code)" placeholder="e.g. B001"/>
    <x-form.input name="row_no"        label="Row No"        type="number" :value="old('row_no', $item?->row_no)"/>
    <x-form.input name="variety"       label="Variety"       required :value="old('variety', $item?->variety)" placeholder="e.g. Musang King"/>
@endsection
