@extends('layouts.app')
@section('title', 'Panen Allocation Report')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Panen (Harvesting) Allocation Report</h5></div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-3"><label>From Date</label><input type="date" name="from" id="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" name="to" id="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-3"><label>Division</label><select name="division" id="division" class="form-control"><option value="ALL">All</option>@foreach($divisions as $div)<option value="{{ $div->division_code }}">{{ $div->division_name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label>Block</label><select name="block" id="block" class="form-control"><option value="ALL">All</option>@foreach($blocks as $blk)<option value="{{ $blk->block_code }}">{{ $blk->block_name }}</option>@endforeach</select></div>
                </div>
                <div class="row mt-2"><div class="col-md-12"><button type="button" id="btn-filter" class="btn btn-primary">Filter</button><a href="{{ route('reporting.transaction.panen-allocation.export') }}" id="btn-export" class="btn btn-success">Export CSV</a></div></div>
            </form>
            <div class="table-responsive"><table id="panen-allocation-table" class="table table-bordered table-sm"><thead><tr><th>No</th><th>Date</th><th>Division</th><th>Block</th><th>Harvester</th><th>Mandor</th><th>Target Bunches</th><th>Loose Fruit</th><th>Ha</th></tr></thead></table></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    let table = $('#panen-allocation-table').DataTable({processing: true, serverSide: true, ajax: {url: "{{ route('reporting.transaction.panen-allocation.index') }}", data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), block: $('#block').val()})}, columns: [{data: 'DT_RowIndex', orderable: false, searchable: false},{data: 'harvesting_date'},{data: 'harvesting_division_code'},{data: 'block_name'},{data: 'harvester_name'},{data: 'mandor_name'},{data: 'target_bunches'},{data: 'target_loose_fruit'},{data: 'allocated_hectare'}], pageLength: 25});
    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {e.preventDefault(); window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val(), block: $('#block').val()});});
});
</script>
@endpush
@endsection
