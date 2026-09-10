@extends('layouts.app')

@section('title', 'OPH by Division Report')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">OPH Report by Division & Block (Aggregated)</h5>
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
                        <a href="{{ route('reporting.transaction.oph-by-division.export') }}" id="btn-export" class="btn btn-success btn-sm">Export</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="oph-div-table" class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Date</th>
                            <th>Division</th>
                            <th>Block</th>
                            <th>Block Name</th>
                            <th>Total Bunches</th>
                            <th>Ripe</th>
                            <th>Loose Fruits</th>
                            <th>Wet</th>
                            <th>Overripe</th>
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
    let table = $('#oph-div-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reporting.transaction.oph-by-division.index') }}",
            data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), block: $('#block').val()})
        },
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'oph_date'},
            {data: 'oph_division_code'},
            {data: 'oph_block_code'},
            {data: 'block_name'},
            {data: 'bunches_total'},
            {data: 'bunches_ripe'},
            {data: 'loose_fruits'},
            {data: 'bunches_wet'},
            {data: 'bunches_overripe'},
        ],
        pageLength: 25
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
