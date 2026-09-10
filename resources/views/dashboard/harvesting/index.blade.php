@extends('layouts.app')

@section('title', 'Harvesting Dashboard')

@section('content')
<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <i class="fa fa-bar-chart"></i>
            <span class="caption-subject bold uppercase">Harvesting Overview</span>
            <span class="caption-helper">Monitor harvesting progress by status</span>
        </div>
    </div>
    <div class="portlet-body">
        <form method="GET" action="{{ route('dashboard.harvesting') }}" class="form-horizontal">
            <input type="hidden" name="form_submit" value="1">
            
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
                        <label class="control-label">Block</label>
                        <select name="block_code" class="form-control select2">
                            <option value="all" {{ $blockCode === 'all' ? 'selected' : '' }}>All Blocks</option>
                            @foreach($blocks as $block)
                                <option value="{{ $block->block_code }}" {{ $blockCode === $block->block_code ? 'selected' : '' }}>
                                    {{ $block->block_code }} - {{ $block->block_name }}
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
        
        @if(request('form_submit'))
            <div class="row">
                <div class="col-md-12 mb-3">
                    <h4 class="block">Harvesting Status Summary</h4>
                    <p class="text-muted">Period: {{ date('d M Y', strtotime($from)) }} to {{ date('d M Y', strtotime($to)) }}</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                    <div class="dashboard-stat blue">
                        <div class="visual">
                            <i class="fa fa-leaf"></i>
                        </div>
                        <div class="details">
                            <div class="number">
                                <span data-counter="counterup" data-value="{{ number_format($stats['in_field']) }}">{{ number_format($stats['in_field']) }}</span>
                            </div>
                            <div class="desc">In Field</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                    <div class="dashboard-stat yellow">
                        <div class="visual">
                            <i class="fa fa-truck"></i>
                        </div>
                        <div class="details">
                            <div class="number">
                                <span data-counter="counterup" data-value="{{ number_format($stats['in_ramp']) }}">{{ number_format($stats['in_ramp']) }}</span>
                            </div>
                            <div class="desc">In Ramp</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                    <div class="dashboard-stat green">
                        <div class="visual">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <div class="details">
                            <div class="number">
                                <span data-counter="counterup" data-value="{{ number_format($stats['in_fdn']) }}">{{ number_format($stats['in_fdn']) }}</span>
                            </div>
                            <div class="desc">In FDN</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                    <div class="dashboard-stat purple">
                        <div class="visual">
                            <i class="fa fa-cubes"></i>
                        </div>
                        <div class="details">
                            <div class="number">
                                <span data-counter="counterup" data-value="{{ number_format($stats['total']) }}">{{ number_format($stats['total']) }}</span>
                            </div>
                            <div class="desc">Total Bunches</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="note note-info">
                        <h4 class="block">Status Definitions</h4>
                        <ul>
                            <li><strong>In Field:</strong> OPH harvested but not yet checked at checkpoint</li>
                            <li><strong>In Ramp:</strong> OPH checked at checkpoint but not yet delivered to FDN</li>
                            <li><strong>In FDN:</strong> OPH completed and recorded at Final Delivery Note</li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> Please select date range and block, then click "View Report" to see harvesting statistics.
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('template_assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" />
<link href="{{ asset('template_assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet" />
<link href="{{ asset('template_assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" />
<style>
.dashboard-stat {
    margin-bottom: 20px;
    padding: 20px;
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}
.dashboard-stat.blue { background-color: #3598dc; }
.dashboard-stat.yellow { background-color: #c49f47; }
.dashboard-stat.green { background-color: #32c5d2; }
.dashboard-stat.purple { background-color: #8775a7; }

.dashboard-stat .visual {
    width: 80px;
    height: 80px;
    display: block;
    float: left;
    padding-top: 10px;
    padding-left: 15px;
    margin-bottom: 15px;
    font-size: 35px;
    line-height: 35px;
}
.dashboard-stat .visual i {
    color: rgba(255,255,255,0.4);
    font-size: 50px;
}
.dashboard-stat .details {
    position: absolute;
    right: 15px;
    padding-top: 15px;
}
.dashboard-stat .details .number {
    font-size: 28px;
    color: #fff;
    font-weight: 300;
    text-align: right;
}
.dashboard-stat .details .desc {
    font-size: 16px;
    color: rgba(255,255,255,0.8);
    text-align: right;
    margin-top: 5px;
}
.mt-3 { margin-top: 30px; }
.mb-3 { margin-bottom: 15px; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('template_assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('template_assets/global/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(function() {
    $('.date-picker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto'
    });
    
    $('.select2').select2({
        placeholder: 'Select block',
        allowClear: false
    });
});
</script>
@endpush
