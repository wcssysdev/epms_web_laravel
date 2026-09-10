@extends('layouts.app')

@section('title', 'Workdone Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Workdone Report</h5>
        </div>
        <div class="card-body">
            <!-- Filters -->
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
                    <div class="col-md-3">
                        <label>Block</label>
                        <select name="block" id="block" class="form-control">
                            <option value="ALL">All Blocks</option>
                            @foreach($blocks as $blk)
                                <option value="{{ $blk->block_code }}">{{ $blk->block_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <button type="button" id="btn-filter" class="btn btn-primary">Filter</button>
                        <a href="{{ route('reporting.transaction.workdone.export') }}" id="btn-export" class="btn btn-success">Export CSV</a>
                    </div>
                </div>
            </form>

            <!-- DataTable -->
            <div class="table-responsive">
                <table id="workdone-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Date</th>
                            <th>Division</th>
                            <th>Block</th>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Activity</th>
                            <th>Quantity</th>
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
    let table = $('#workdone-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.transaction.workdone.index') }}",
            data: function(d) {
                d.from = $('#from').val();
                d.to = $('#to').val();
                d.division = $('#division').val();
                d.block = $('#block').val();
            }
        },
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'created_at', name: 'created_at'},
            {data: 'division_code', name: 'division_code'},
            {data: 'block_code', name: 'block_code'},
            {data: 'employee_code', name: 'employee_code'},
            {data: 'employee_name', name: 'employee_name'},
            {data: 'activity_name', name: 'activity_name'},
            {data: 'quantity', name: 'quantity'},
            {data: 'notes', name: 'notes'},
        ]
    });

    $('#btn-filter').click(function() {
        table.draw();
    });

    $('#btn-export').click(function(e) {
        e.preventDefault();
        const params = new URLSearchParams({
            from: $('#from').val(),
            to: $('#to').val(),
            division: $('#division').val(),
            block: $('#block').val()
        });
        window.location.href = $(this).attr('href') + '?' + params.toString();
    });
});
</script>
@endpush
@endsection
