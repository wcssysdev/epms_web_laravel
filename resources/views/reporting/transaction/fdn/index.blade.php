@extends('layouts.app')
@section('title', 'FDN Report')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Foundation/Delivery (FDN) Report</h5></div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-3"><label>From Date</label><input type="date" name="from" id="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" name="to" id="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2"><label>Division</label><select name="division" id="division" class="form-control"><option value="ALL">All</option>@foreach($divisions as $div)<option value="{{ $div->division_code }}">{{ $div->division_name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label>Destination</label><select name="destination" id="destination" class="form-control"><option value="ALL">All</option>@foreach($destinations as $d)<option value="{{ $d->destination_code }}">{{ $d->destination_name }}</option>@endforeach</select></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="button" id="btn-filter" class="btn btn-primary btn-sm mr-1">Filter</button><a href="{{ route('reporting.transaction.fdn.export') }}" id="btn-export" class="btn btn-success btn-sm">Export</a></div>
                </div>
            </form>
            <div class="table-responsive"><table id="fdn-table" class="table table-bordered table-sm"><thead><tr><th>No</th><th>Date</th><th>Division</th><th>License</th><th>Destination</th><th>Clerk</th><th>Driver</th><th>Bruto</th><th>Tarra</th><th>Tonnage</th><th>Loader</th></tr></thead></table></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    let table = $('#fdn-table').DataTable({processing: true, serverSide: true, ajax: {url: "{{ route('reporting.transaction.fdn.index') }}", data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), destination: $('#destination').val()})}, columns: [{data: 'DT_RowIndex', orderable: false, searchable: false},{data: 'fdn_date'},{data: 'fdn_division_code'},{data: 'fdn_license_number'},{data: 'fdn_deliver_to_code'},{data: 'fdn_kerani_kirim_employee_name'},{data: 'fdn_driver_name'},{data: 'fdn_bruto'},{data: 'fdn_tarra'},{data: 'fdn_actual_tonnage'},{data: 'fdn_loader_employee_name'}], pageLength: 25});
    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {e.preventDefault(); window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), destination: $('#destination').val()});});
});
</script>
@endpush
@endsection
