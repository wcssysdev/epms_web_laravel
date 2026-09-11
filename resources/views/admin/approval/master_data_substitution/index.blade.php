@extends('layouts.app')

@section('title', 'Master Data Substitution')

@section('content')
<div class="page-head">
    <div class="page-title">
        <h1>Master Data Substitution</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade in">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade in">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif
        @if($systemLocked)
            <div class="alert alert-warning">
                <i class="fa fa-lock"></i> <strong>System Locked:</strong> System is currently locked. Adding or modifying substitutions is restricted.
            </div>
        @endif

        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption font-blue">
                    <i class="fa fa-database font-blue"></i>
                    <span class="caption-subject bold uppercase">Master Data Substitution</span>
                    <span class="caption-helper">Temporary Master Data Access Delegation</span>
                </div>
                <div class="actions">
                    @if(!$systemLocked)
                    <a href="{{ route('admin.master-data-substitution.create') }}" class="btn btn-sm btn-primary">
                        <i class="fa fa-plus"></i> Add Substitution
                    </a>
                    @endif
                </div>
            </div>
            <div class="portlet-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover" id="master-data-substitution-table" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Substituted User</th>
                                <th style="width: 140px;">Valid From</th>
                                <th style="width: 140px;">Valid To</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title bold"><i class="fa fa-exclamation-triangle text-danger"></i> Confirm Delete</h4>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this Master Data substitution record?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    var table = $('#master-data-substitution-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.master-data-substitution.index") }}',
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'target_employee_display', name: 'target_employee_name'},
            {data: 'substitution_from_formatted', name: 'substitution_from'},
            {data: 'substitution_to_formatted', name: 'substitution_to'},
            {data: 'status', name: 'status', orderable: false, searchable: false, className: 'text-center'},
            {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center'}
        ],
        order: [[2, 'desc']]
    });

    var deleteId = null;
    $(document).on('click', '.btn-delete', function() {
        deleteId = $(this).data('id');
        $('#deleteModal').modal('show');
    });

    $('#confirm-delete').on('click', function() {
        if (!deleteId) return;

        $.ajax({
            url: '{{ url("admin/master-data-substitution") }}/' + deleteId,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#deleteModal').modal('hide');
                if (response.success) {
                    table.ajax.reload(null, false);
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message);
                    } else {
                        alert(response.message);
                    }
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message);
                    } else {
                        alert(response.message);
                    }
                }
            },
            error: function(xhr) {
                $('#deleteModal').modal('hide');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete substitution.';
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            }
        });
    });
});
</script>
@endpush
