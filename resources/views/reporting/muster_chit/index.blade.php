@extends('layouts.app')

@section('title', 'Muster Chit Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Muster Chit Report (Workdone + Attendance Summary)</h5>
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
                        <a href="{{ route('reporting.muster-chit.export') }}" id="btn-export" class="btn btn-success">Export CSV</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="muster-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Date</th>
                            <th>Attendance Type</th>
                            <th>Activity</th>
                            <th>Total Quantity</th>
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
    let table = $('#muster-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.muster-chit.index') }}",
            data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val()})
        },
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'employee_code'},
            {data: 'employee_name'},
            {data: 'attendance_date'},
            {data: 'attendance_type'},
            {data: 'activity_name'},
            {data: 'total_quantity'},
        ]
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
