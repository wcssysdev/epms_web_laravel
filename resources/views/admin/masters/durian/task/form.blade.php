@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="estate_code"   label="Estate Code"   required :value="old('estate_code', $item?->estate_code)" placeholder="e.g. SG"/>
    <x-form.input name="division_code" label="Division Code" required :value="old('division_code', $item?->division_code)" placeholder="e.g. D01"/>
    <x-form.input name="block_code"    label="Block Code"    required :value="old('block_code', $item?->block_code)" placeholder="e.g. B001"/>
    <x-form.input name="task_no"       label="Task No"       required :value="old('task_no', $item?->task_no)"/>
    <x-form.input name="row_no"        label="Row No"        type="number" :value="old('row_no', $item?->row_no)"/>
    <x-form.input name="task_validity" label="Task Validity" type="date" :value="old('task_validity', $item?->task_validity)"/>
@endsection
