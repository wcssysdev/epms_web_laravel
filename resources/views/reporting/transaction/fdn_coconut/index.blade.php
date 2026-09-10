@extends('layouts.app')

@section('title', 'FDN Coconut Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">FDN Coconut Report (Foundation Coconut Delivery)</h5>
        </div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <label>From Date</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ $from }}">
                    </div>
                    <div class="col-md-4">
                        <label>To Date</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ $to }}">
                    </div>
                    <div class="col-md-4">
                        <label>Division</label>
                        <select name="division" id="division" class="form-control">
                            <option value="ALL">All Divisions</option>
                            @foreach($divisions as $div)
                                <option value="{{ $div->division_code }}">{{ $div->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <button type="button" id="btn-filter" class="btn btn-primary">Filter</button>
                        <a href="{{ route('reporting.transaction.fdn-coconut.export') }}" id="btn-export" class="btn btn-success">Export CSV</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="fdn-coconut-table" class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Date</th>
                            <th>Division</th>
                            <th>License No</th>
                            <th>Driver</th>
                            <th>Transport Clerk</th>
                            <th>Nursery</th>
                            <th>Destination</th>
                            <th>SO</th>
                            <th>Total OPH</th>
                            <th>Bruto</th>
                            <th>Tarra</th>
                            <th>Actual Tonnage</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    let table = $('#fdn-coconut-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.transaction.fdn-coconut.index') }}",
            data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val()})
        },
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'fdn_date'},
            {data: 'coconut_fdn_division_code'},
            {data: 'coconut_fdn_license_number'},
            {data: 'coconut_fdn_driver_name'},
            {data: 'coconut_fdn_kerani_kirim_employee_name'},
            {data: 'coconut_fdn_is_nursery'},
            {data: 'coconut_fdn_destination'},
            {data: 'coconut_fdn_sales_order'},
            {data: 'coconut_fdn_total_oph'},
            {data: 'coconut_fdn_bruto'},
            {data: 'coconut_fdn_tarra'},
            {data: 'coconut_fdn_actual_tonnage'},
        ],
        pageLength: 25,
        scrollX: true
    });

    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val()});
    });
});
</script>
@endpush
@endsection
