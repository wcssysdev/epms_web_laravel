@extends('layouts.app')
@section('title', 'CP Report')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Collection Point (CP) Report</h5></div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-3"><label>From Date</label><input type="date" name="from" id="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" name="to" id="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2"><label>Division</label><select name="division" id="division" class="form-control"><option value="ALL">All</option>@foreach($divisions as $div)<option value="{{ $div->division_code }}">{{ $div->division_name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label>Ramp</label><select name="ramp" id="ramp" class="form-control"><option value="ALL">All</option>@foreach($ramps as $r)<option value="{{ $r->receiving_point_code }}">{{ $r->receiving_point_name }}</option>@endforeach</select></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="button" id="btn-filter" class="btn btn-primary btn-sm mr-1">Filter</button><a href="{{ route('reporting.transaction.cp.export') }}" id="btn-export" class="btn btn-success btn-sm">Export</a></div>
                </div>
            </form>
            <div class="table-responsive"><table id="cp-table" class="table table-bordered table-sm"><thead><tr><th>No</th><th>Date</th><th>Division</th><th>License</th><th>Ramp</th><th>Clerk</th><th>Type</th><th>Bruto</th><th>Tarra</th><th>Netto</th><th>Loader</th><th>%</th></tr></thead></table></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    let table = $('#cp-table').DataTable({processing: true, serverSide: true, ajax: {url: "{{ route('reporting.transaction.cp.index') }}", data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), ramp: $('#ramp').val()})}, columns: [{data: 'DT_RowIndex', orderable: false, searchable: false},{data: 'cp_date'},{data: 'cp_division_code'},{data: 'cp_license_number'},{data: 'cp_receiving_point_code'},{data: 'cp_kerani_kirim_employee_name'},{data: 'cp_type'},{data: 'cp_bruto'},{data: 'cp_tarra'},{data: 'cp_netto'},{data: 'cp_loader_employee_name'},{data: 'cp_loader_percentage'}], pageLength: 25});
    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {e.preventDefault(); window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), ramp: $('#ramp').val()});});
});
</script>
@endpush
@endsection
