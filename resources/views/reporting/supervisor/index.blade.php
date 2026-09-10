@extends('layouts.app')
@section('title', 'Supervisor Report')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Supervisor Performance Report</h5></div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-4"><label>From Date</label><input type="date" name="from" id="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-4"><label>To Date</label><input type="date" name="to" id="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-4"><label>Division</label><select name="division" id="division" class="form-control"><option value="ALL">All Divisions</option>@foreach($divisions as $div)<option value="{{ $div->division_code }}">{{ $div->division_name }}</option>@endforeach</select></div>
                </div>
                <div class="row mt-2"><div class="col-md-12"><button type="button" id="btn-filter" class="btn btn-primary">Filter</button><a href="{{ route('reporting.supervisor.export') }}" id="btn-export" class="btn btn-success">Export CSV</a></div></div>
            </form>
            <div class="table-responsive"><table id="supervisor-table" class="table table-bordered"><thead><tr><th>No</th><th>Supervisor Code</th><th>Supervisor Name</th><th>Division</th><th>Total OPH</th><th>Total Bunches</th><th>Total Attendance</th></tr></thead></table></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    let table = $('#supervisor-table').DataTable({processing: true, serverSide: true, ajax: {url: "{{ route('reporting.supervisor.index') }}", data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val()})}, columns: [{data: 'DT_RowIndex', orderable: false, searchable: false},{data: 'mandor_employee_code'},{data: 'mandor_employee_name'},{data: 'division_code'},{data: 'total_oph'},{data: 'total_bunches'},{data: 'total_attendance'}], pageLength: 25});
    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {e.preventDefault(); window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val()});});
});
</script>
@endpush
@endsection
