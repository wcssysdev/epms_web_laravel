@extends('layouts.app')

@section('title', 'Add Master Data Substitution')

@section('content')
<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <i class="fa fa-plus"></i>
            <span class="caption-subject bold uppercase">Add Master Data Substitution</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.master-data-substitution.index') }}" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    <div class="portlet-body form">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('admin.master-data-substitution.store') }}" method="POST" class="form-horizontal">
            @csrf
            <div class="form-body">
                <div class="form-group">
                    <label class="control-label col-md-3">User <span class="required">*</span></label>
                    <div class="col-md-6">
                        <select name="approval_substitution_employee_code" class="form-control select2" required>
                            <option value="">Select User</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('approval_substitution_employee_code') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->employee_code }}) - {{ ucfirst(str_replace('_', ' ', $user->role_code)) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="help-block">Person who will delegate master data access</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-md-3">Substitute <span class="required">*</span></label>
                    <div class="col-md-6">
                        <select name="approval_substitution_employee_code_target" class="form-control select2" required>
                            <option value="">Select Substitute</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('approval_substitution_employee_code_target') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->employee_code }}) - {{ ucfirst(str_replace('_', ' ', $user->role_code)) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="help-block">Person who will receive temporary master data access</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-md-3">Valid From <span class="required">*</span></label>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" name="approval_substitution_from" class="form-control date-picker" 
                                   value="{{ old('approval_substitution_from') }}" required readonly>
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-md-3">Valid To <span class="required">*</span></label>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" name="approval_substitution_to" class="form-control date-picker" 
                                   value="{{ old('approval_substitution_to') }}" required readonly>
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <div class="row">
                    <div class="col-md-offset-3 col-md-6">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-check"></i> Save Substitution
                        </button>
                        <a href="{{ route('admin.master-data-substitution.index') }}" class="btn btn-default">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('template_assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" />
<link href="{{ asset('template_assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet" />
<link href="{{ asset('template_assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" />
@endpush

@push('scripts')
<script src="{{ asset('template_assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('template_assets/global/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(function() {
    $('.select2').select2({
        placeholder: 'Select an option',
        allowClear: true
    });

    $('.date-picker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto'
    });
});
</script>
@endpush
