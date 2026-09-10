@extends('layouts.app')

@section('title', 'Attendance Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Attendance Report</h5>
        </div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ $from }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ $to }}">
                    </div>
                    <div class="col-md-3">
                        <label>Division</label>
                        <select name="division" id="division" class="form-control">
                            <option value="ALL">All Divisions</option>
                            @foreach($divisions as $div)
                                <option value="{{ $div->division_code }}">{{ $div->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" id="btn-filter" class="btn btn-primary mr-2">Filter</button>
                        <a href="{{ route('reporting.transaction.attendance.export') }}" id="btn-export" class="btn btn-success">Export CSV</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="attendance-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Date</th>
                            <th>Division</th>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Attendance Type</th>
                            <th>Notes</th>
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
    let table = $('#attendance-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.transaction.attendance.index') }}",
            data: function(d) {
                d.from = $('#from').val();
                d.to = $('#to').val();
                d.division = $('#division').val();
            }
        },
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'attendance_date'},
            {data: 'division_code'},
            {data: 'employee_code'},
            {data: 'employee_name'},
            {data: 'attendance_type'},
            {data: 'notes'},
        ]
    });

    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {
        e.preventDefault();
        const params = new URLSearchParams({
            from: $('#from').val(),
            to: $('#to').val(),
            division: $('#division').val()
        });
        window.location.href = $(this).attr('href') + '?' + params;
    });
});
</script>
@endpush
@endsection
