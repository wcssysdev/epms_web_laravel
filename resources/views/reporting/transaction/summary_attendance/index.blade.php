@extends('layouts.app')

@section('title', 'Summary Attendance Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Summary Attendance Report</h5>
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
                    <div class="col-md-2">
                        <label>Division</label>
                        <select name="division" id="division" class="form-control">
                            <option value="ALL">All</option>
                            @foreach($divisions as $div)
                                <option value="{{ $div->division_code }}">{{ $div->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Attendance Type</label>
                        <select name="attendance_code" id="attendance_code" class="form-control">
                            <option value="ALL">All</option>
                            @foreach($attendance_types as $att)
                                <option value="{{ $att->attendance_code }}">{{ $att->attendance_desc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Report Type</label>
                        <select name="report_type" id="report_type" class="form-control">
                            <option value="EMPLOYEE">By Employee</option>
                            <option value="CATEGORY">By Category</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <button type="button" id="btn-filter" class="btn btn-primary">Filter</button>
                        <a href="{{ route('reporting.transaction.summary-attendance.export') }}" id="btn-export" class="btn btn-success">Export CSV</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="summary-att-table" class="table table-bordered">
                    <thead id="table-header-employee">
                        <tr>
                            <th>No</th>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Attendance Code</th>
                            <th>Attendance Type</th>
                            <th>Total Days</th>
                        </tr>
                    </thead>
                    <thead id="table-header-category" style="display:none;">
                        <tr>
                            <th>No</th>
                            <th>Attendance Code</th>
                            <th>Attendance Type</th>
                            <th>Total Records</th>
                            <th>Unique Employees</th>
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
    let table = $('#summary-att-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.transaction.summary-attendance.index') }}",
            data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), attendance_code: $('#attendance_code').val(), report_type: $('#report_type').val()})
        },
        columns: getColumnsForReportType('EMPLOYEE')
    });

    function getColumnsForReportType(type) {
        if (type === 'CATEGORY') {
            return [
                {data: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'attendance_code'},
                {data: 'attendance_desc'},
                {data: 'total_count'},
                {data: 'unique_employees'},
            ];
        }
        return [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'attendance_employee_code'},
            {data: 'employee_name'},
            {data: 'attendance_code'},
            {data: 'attendance_desc'},
            {data: 'total_days'},
        ];
    }

    $('#report_type').change(function() {
        const reportType = $(this).val();
        if (reportType === 'CATEGORY') {
            $('#table-header-employee').hide();
            $('#table-header-category').show();
        } else {
            $('#table-header-employee').show();
            $('#table-header-category').hide();
        }
    });

    $('#btn-filter').click(() => {
        table.destroy();
        table = $('#summary-att-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('reporting.transaction.summary-attendance.index') }}",
                data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), attendance_code: $('#attendance_code').val(), report_type: $('#report_type').val()})
            },
            columns: getColumnsForReportType($('#report_type').val())
        });
    });

    $('#btn-export').click(function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), attendance_code: $('#attendance_code').val(), report_type: $('#report_type').val()});
    });
});
</script>
@endpush
@endsection
