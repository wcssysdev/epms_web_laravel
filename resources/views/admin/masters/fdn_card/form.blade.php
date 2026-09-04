@extends('admin.grouping._shared.form')

@section('form-fields')
    <x-form.input name="fdn_card_id" label="Card Code" required
                  :value="old('fdn_card_id', $item?->fdn_card_id)"
                  placeholder="e.g. FDN001"/>

    <x-form.select name="division_code" label="Division" required
                   :value="old('division_code', $item?->division_code)">
        <option value="">— Select Division —</option>
        @foreach($divisions as $d)
            <option value="{{ $d->division_code }}"
                @selected(old('division_code', $item?->division_code) === $d->division_code)>
                {{ $d->division_code }} — {{ $d->division_name }}
            </option>
        @endforeach
    </x-form.select>
@endsection
