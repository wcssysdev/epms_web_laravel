@extends('layouts.app')

@section('title', 'Audit Trail Detail')

@section('content')
<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <i class="fa fa-history"></i>
            <span class="caption-subject bold uppercase">Audit Trail Detail</span>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-default" onclick="window.close()">
                <i class="fa fa-times"></i> Close
            </button>
        </div>
    </div>
    <div class="portlet-body">
        <div class="row">
            <div class="col-md-12">
                <table class="table table-bordered">
                    <tr>
                        <th width="200">Audit ID</th>
                        <td>{{ $audit->audit_trail_id }}</td>
                    </tr>
                    <tr>
                        <th>Date & Time</th>
                        <td>{{ date('d-m-Y', strtotime($audit->audit_trail_created_date)) }} {{ $audit->audit_trail_created_time }}</td>
                    </tr>
                    <tr>
                        <th>Transaction</th>
                        <td>{{ $audit->audit_trail_transaction }}</td>
                    </tr>
                    <tr>
                        <th>Action Type</th>
                        <td>
                            @if($audit->audit_trail_type === 'CREATE')
                                <span class="badge badge-success">CREATE</span>
                            @elseif($audit->audit_trail_type === 'UPDATE')
                                <span class="badge badge-warning">UPDATE</span>
                            @elseif($audit->audit_trail_type === 'DELETE')
                                <span class="badge badge-danger">DELETE</span>
                            @else
                                {{ $audit->audit_trail_type }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>User</th>
                        <td>{{ $audit->audit_trail_user_name }} ({{ $audit->audit_trail_user_code }})</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($audit->audit_trail_type === 'UPDATE' && !empty($before) && !empty($after))
            <hr>
            <h4>Changes Comparison</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Before</strong>
                        </div>
                        <div class="panel-body">
                            <pre style="max-height: 400px; overflow-y: auto;">{{ json_encode($before, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>After</strong>
                        </div>
                        <div class="panel-body">
                            <pre style="max-height: 400px; overflow-y: auto;">{{ json_encode($after, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <hr>
            <h4>Full Data</h4>
            <div class="panel panel-default">
                <div class="panel-body">
                    <pre style="max-height: 400px; overflow-y: auto;">{{ $audit->audit_trail_description }}</pre>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
pre {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    font-size: 12px;
}
</style>
@endpush
