@extends('layouts.app')

@section('title', 'Manager Substitution')

@section('content')
<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <i class="fa fa-exchange"></i>
            <span class="caption-subject bold uppercase">Manager Substitution</span>
            <span class="caption-helper">Estate Manager → Assistant Manager</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.substitution.create') }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> Add Substitution
            </a>
        </div>
    </div>
    <div class="portlet-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('error') }}
            </div>
        @endif

        <table class="table table-striped table-bordered table-hover" id="substitution-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Estate Manager</th>
                    <th>Substitute (Assistant Manager)</th>
                    <th>Valid From</th>
                    <th>Valid To</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Confirm Delete</h4>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this substitution?
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
    var table = $('#substitution-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.substitution.index") }}',
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'approval_substitution_employee_name', name: 'approval_substitution_employee_name'},
            {data: 'approval_substitution_employee_name_target', name: 'approval_substitution_employee_name_target'},
            {data: 'approval_substitution_from', name: 'approval_substitution_from'},
            {data: 'approval_substitution_to', name: 'approval_substitution_to'},
            {data: 'status', name: 'status', orderable: false},
            {data: 'action', name: 'action', orderable: false, searchable: false}
        ],
        order: [[3, 'desc']]
    });

    var deleteId = null;
    $(document).on('click', '.btn-delete', function() {
        deleteId = $(this).data('id');
        $('#deleteModal').modal('show');
    });

    $('#confirm-delete').on('click', function() {
        if (deleteId) {
            $.ajax({
                url: '{{ route("admin.substitution.index") }}/' + deleteId,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    $('#deleteModal').modal('hide');
                    if (response.success) {
                        table.ajax.reload();
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    $('#deleteModal').modal('hide');
                    toastr.error('Failed to delete substitution.');
                }
            });
        }
    });
});
</script>
@endpush
