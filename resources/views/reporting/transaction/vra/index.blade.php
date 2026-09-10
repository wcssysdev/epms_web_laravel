@extends('layouts.app')
@section('title', 'VRA Report')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Vehicle Request Authorization (VRA) Report</h5></div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-3"><label>From Date</label><input type="date" name="from" id="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" name="to" id="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-3"><label>Work Center</label><select name="work_center" id="work_center" class="form-control"><option value="ALL">All</option>@foreach($work_centers as $wc)<option value="{{ $wc->work_center_code }}">{{ $wc->work_center_name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label>Work Type</label><select name="worktype" id="worktype" class="form-control"><option value="ALL">All</option>@foreach($worktypes as $wt)<option value="{{ $wt->worktype_code }}">{{ $wt->worktype_name }}</option>@endforeach</select></div>
                </div>
                <div class="row mt-2"><div class="col-md-12"><button type="button" id="btn-filter" class="btn btn-primary">Filter</button><a href="{{ route('reporting.transaction.vra.export') }}" id="btn-export" class="btn btn-success">Export CSV</a></div></div>
            </form>
            <div class="table-responsive"><table id="vra-table" class="table table-bordered table-sm"><thead><tr><th>No</th><th>Date</th><th>VRA Code</th><th>Work Center</th><th>Work Type</th><th>Start</th><th>End</th><th>Measurement</th><th>Actual</th><th>License</th></tr></thead></table></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    let table = $('#vra-table').DataTable({processing: true, serverSide: true, ajax: {url: "{{ route('reporting.transaction.vra.index') }}", data: d => ({...d, from: $('#from').val(), to: $('#to').val(), work_center: $('#work_center').val(), worktype: $('#worktype').val()})}, columns: [{data: 'DT_RowIndex', orderable: false, searchable: false},{data: 'posting_date'},{data: 'vra_code'},{data: 'work_center_name'},{data: 'worktype_name'},{data: 'vra_start_time'},{data: 'vra_end_time'},{data: 'vra_measurement'},{data: 'vra_actual'},{data: 'vra_license_number'}], pageLength: 25});
    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {e.preventDefault(); window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), work_center: $('#work_center').val(), worktype: $('#worktype').val()});});
});
</script>
@endpush
@endsection
