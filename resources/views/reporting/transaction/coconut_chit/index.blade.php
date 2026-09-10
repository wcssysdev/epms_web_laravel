@extends('layouts.app')

@section('title', 'Coconut Harvesting Chit Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Coconut Harvesting Chit Report</h5>
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
                        <label>Block</label>
                        <select name="block" id="block" class="form-control">
                            <option value="ALL">All</option>
                            @foreach($blocks as $blk)
                                <option value="{{ $blk->block_code }}">{{ $blk->block_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" id="btn-filter" class="btn btn-primary btn-sm mr-1">Filter</button>
                        <a href="{{ route('reporting.transaction.coconut-chit.export') }}" id="btn-export" class="btn btn-success btn-sm">Export</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="coconut-table" class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Date</th>
                            <th>Division</th>
                            <th>Block</th>
                            <th>Harvester Code</th>
                            <th>Harvester Name</th>
                            <th>Checker Code</th>
                            <th>Checker Name</th>
                            <th>Total Nuts</th>
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
    let table = $('#coconut-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.transaction.coconut-chit.index') }}",
            data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), block: $('#block').val()})
        },
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'created_at'},
            {data: 'division_code'},
            {data: 'block_code'},
            {data: 'harvester_employee_code'},
            {data: 'harvester_name'},
            {data: 'checker_employee_code'},
            {data: 'checker_name'},
            {data: 'nuts_total'},
        ]
    });

    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), block: $('#block').val()});
    });
});
</script>
@endpush
@endsection
