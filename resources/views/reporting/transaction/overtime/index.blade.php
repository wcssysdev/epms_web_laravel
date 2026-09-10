@extends('layouts.app')

@section('title', 'Overtime Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Overtime Report</h5>
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
                        <a href="{{ route('reporting.transaction.overtime.export') }}" id="btn-export" class="btn btn-success">Export CSV</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="overtime-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Date</th>
                            <th>Division</th>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Activity</th>
                            <th>Duration (Hours)</th>
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
    let table = $('#overtime-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.transaction.overtime.index') }}",
            data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val()})
        },
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'overtime_date'},
            {data: 'division_code'},
            {data: 'employee_code'},
            {data: 'employee_name'},
            {data: 'activity_name'},
            {data: 'duration_hours'},
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
