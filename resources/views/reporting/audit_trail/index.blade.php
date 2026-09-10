@extends('layouts.app')

@section('title', 'Audit Trail Report')

@section('content')
<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <i class="fa fa-history"></i>
            <span class="caption-subject bold uppercase">Audit Trail Report</span>
            <span class="caption-helper">Track all user actions and data changes</span>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-success" id="btn-export">
                <i class="fa fa-download"></i> Export CSV
            </button>
        </div>
    </div>
    <div class="portlet-body">
        <form method="GET" id="filter-form" class="form-horizontal">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="control-label">Date From</label>
                        <div class="input-group">
                            <input type="text" name="from" class="form-control date-picker" value="{{ $from }}" required readonly>
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="control-label">Date To</label>
                        <div class="input-group">
                            <input type="text" name="to" class="form-control date-picker" value="{{ $to }}" required readonly>
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="control-label">Transaction Type</label>
                        <select name="transaction_code" class="form-control">
                            <option value="ALL" {{ $transaction_code === 'ALL' ? 'selected' : '' }}>All Transactions</option>
                            @foreach($transaction_types as $type)
                                <option value="{{ $type }}" {{ $transaction_code === $type ? 'selected' : '' }}>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="control-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-search"></i> View Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <hr>
        
        <table class="table table-striped table-bordered table-hover" id="audit-trail-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Date & Time</th>
                    <th>Transaction</th>
                    <th>Type</th>
                    <th>User</th>
                    <th>Description</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('template_assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" />
<link href="{{ asset('template_assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" />
@endpush

@push('scripts')
<script src="{{ asset('template_assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('template_assets/global/plugins/datatables/datatables.all.min.js') }}"></script>
<script>
$(function() {
    $('.date-picker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto'
    });

    var table = $('#audit-trail-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("reporting.audit-trail.index") }}',
            data: function(d) {
                d.from = $('input[name="from"]').val();
                d.to = $('input[name="to"]').val();
                d.transaction_code = $('select[name="transaction_code"]').val();
            }
        },
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'date_time', name: 'audit_trail_created_date'},
            {data: 'audit_trail_transaction', name: 'audit_trail_transaction'},
            {data: 'audit_trail_type', name: 'audit_trail_type'},
            {data: 'audit_trail_user_name', name: 'audit_trail_user_name'},
            {data: 'audit_trail_description', name: 'audit_trail_description'},
            {data: 'action', name: 'action', orderable: false, searchable: false}
        ],
        order: [[1, 'desc']],
        pageLength: 25
    });

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    $('#btn-export').on('click', function() {
        var params = $('#filter-form').serialize();
        window.location.href = '{{ route("reporting.audit-trail.export") }}?' + params;
    });
});
</script>
@endpush
