@extends('layouts.app')
@section('title', 'Device Report')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Device Activity Report</h5></div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-4"><label>From Date</label><input type="date" name="from" id="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-4"><label>To Date</label><input type="date" name="to" id="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-4"><label>Device</label><select name="device_id" id="device_id" class="form-control"><option value="ALL">All Devices</option>@foreach($devices as $dev)<option value="{{ $dev->device_id }}">{{ $dev->device_name }}</option>@endforeach</select></div>
                </div>
                <div class="row mt-2"><div class="col-md-12"><button type="button" id="btn-filter" class="btn btn-primary">Filter</button><a href="{{ route('reporting.device.export') }}" id="btn-export" class="btn btn-success">Export CSV</a></div></div>
            </form>
            <div class="table-responsive"><table id="device-table" class="table table-bordered table-sm"><thead><tr><th>No</th><th>Date</th><th>Device ID</th><th>Device Name</th><th>User ID</th><th>User Name</th><th>Activity Type</th><th>Count</th></tr></thead></table></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    let table = $('#device-table').DataTable({processing: true, serverSide: true, ajax: {url: "{{ route('reporting.device.index') }}", data: d => ({...d, from: $('#from').val(), to: $('#to').val(), device_id: $('#device_id').val()})}, columns: [{data: 'DT_RowIndex', orderable: false, searchable: false},{data: 'log_date'},{data: 'device_id'},{data: 'device_name'},{data: 'user_id'},{data: 'user_name'},{data: 'activity_type'},{data: 'activity_count'}], pageLength: 25});
    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {e.preventDefault(); window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), device_id: $('#device_id').val()});});
});
</script>
@endpush
@endsection
