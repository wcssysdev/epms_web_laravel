@extends('layouts.app')

@section('title', 'Edit Master Data Substitution')

@section('content')
<div class="page-head">
    <div class="page-title">
        <h1>Edit Master Data Substitution</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade in">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-exclamation-circle"></i> <strong>Validation Errors:</strong>
                <ul class="margin-bottom-0 margin-top-10">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
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
                <i class="fa fa-lock"></i> <strong>System Locked:</strong> System is currently locked. Modifications cannot be saved.
            </div>
        @endif

        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption font-blue">
                    <i class="fa fa-pencil font-blue"></i>
                    <span class="caption-subject bold uppercase">Edit Master Data Substitution</span>
                </div>
                <div class="actions">
                    <a href="{{ route('admin.master-data-substitution.index') }}" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="portlet-body form">
                <form action="{{ route('admin.master-data-substitution.update', $substitution->id) }}" method="POST" class="form-horizontal">
                    @csrf
                    @method('PUT')
                    <div class="form-body">
                        <div class="form-group {{ $errors->has('target_employee_code') ? 'has-error' : '' }}">
                            <label class="control-label col-md-3">Targeted User <span class="required">*</span></label>
                            <div class="col-md-6">
                                <select name="target_employee_code" class="form-control select2" required {{ $systemLocked ? 'disabled' : '' }}>
                                    <option value="">-- Choose User --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('target_employee_code', $substitution->target_employee_code) == $user->id ? 'selected' : '' }}>
                                            {{ $user->user_name }} {{ $user->employee_code ? '(' . $user->employee_code . ')' : '' }} - {{ ucfirst(str_replace('_', ' ', $user->role_name ?? $user->role_code)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="help-block">Select the employee who will receive temporary Master Data access</span>
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('substitution_from') ? 'has-error' : '' }}">
                            <label class="control-label col-md-3">Valid From <span class="required">*</span></label>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" name="substitution_from" class="form-control date-picker" 
                                           value="{{ old('substitution_from', $substitution->substitution_from ? $substitution->substitution_from->format('Y-m-d') : '') }}" required readonly {{ $systemLocked ? 'disabled' : '' }}>
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('substitution_to') ? 'has-error' : '' }}">
                            <label class="control-label col-md-3">Valid To <span class="required">*</span></label>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" name="substitution_to" class="form-control date-picker" 
                                           value="{{ old('substitution_to', $substitution->substitution_to ? $substitution->substitution_to->format('Y-m-d') : '') }}" required readonly {{ $systemLocked ? 'disabled' : '' }}>
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <div class="row">
                            <div class="col-md-offset-3 col-md-6">
                                @if(!$systemLocked)
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-check"></i> Update Substitution
                                </button>
                                @endif
                                <a href="{{ route('admin.master-data-substitution.index') }}" class="btn btn-default">Cancel</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
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
        theme: 'bootstrap',
        width: '100%'
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
