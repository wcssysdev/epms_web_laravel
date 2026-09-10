@extends('layouts.app')
@section('title', 'GR-R Report')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Goods Receipt Reversal (GR-R) Report</h5></div>
        <div class="card-body">
            <form id="filter-form" class="mb-4">
                <div class="row">
                    <div class="col-md-4"><label>From Date</label><input type="date" name="from" id="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-4"><label>To Date</label><input type="date" name="to" id="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-4"><label>Division</label><select name="division" id="division" class="form-control"><option value="ALL">All Divisions</option>@foreach($divisions as $div)<option value="{{ $div->division_code }}">{{ $div->division_name }}</option>@endforeach</select></div>
                </div>
                <div class="row mt-2"><div class="col-md-12"><button type="button" id="btn-filter" class="btn btn-primary">Filter</button><a href="{{ route('reporting.transaction.gr-r.export') }}" id="btn-export" class="btn btn-success">Export CSV</a></div></div>
            </form>
            <div class="table-responsive"><table id="grr-table" class="table table-bordered table-sm"><thead><tr><th>No</th><th>Date</th><th>Division</th><th>MVT</th><th>Vendor</th><th>Vendor Name</th><th>Material</th><th>Mat Doc</th><th>Qty</th><th>UOM</th><th>PO</th></tr></thead></table></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    let table = $('#grr-table').DataTable({processing: true, serverSide: true, ajax: {url: "{{ route('reporting.transaction.gr-r.index') }}", data: d => ({...d, from: $('#from').val(), to: $('#to').val(), division: $('#division').val()})}, columns: [{data: 'DT_RowIndex', orderable: false, searchable: false},{data: 'posting_date'},{data: 'gr_r_division_code'},{data: 'gr_r_mvt'},{data: 'gr_r_vendor'},{data: 'vendor_name'},{data: 'gr_r_material'},{data: 'gr_r_mat_doc'},{data: 'gr_r_quantity'},{data: 'gr_r_uom'},{data: 'gr_r_po_number'}], pageLength: 25});
    $('#btn-filter').click(() => table.draw());
    $('#btn-export').click(function(e) {e.preventDefault(); window.location.href = $(this).attr('href') + '?' + $.param({from: $('#from').val(), to: $('#to').val(), division: $('#division').val()});});
});
</script>
@endpush
@endsection
