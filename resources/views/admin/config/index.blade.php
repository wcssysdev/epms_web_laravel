@extends('layouts.app')
@section('title', 'Estate Settings')
@section('page-title', 'Estate Settings')
@section('page-subtitle', 'Configure estate, integration type, and system settings')
@section('breadcrumb')
    <li>Estate Settings</li>
@endsection
@section('content')
<div class="card bg-base-100 shadow-sm">
    <div class="card-body">
        @if($config)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-base-content/50 uppercase font-bold mb-1">Estate</p>
                <p class="font-semibold">{{ $config->estate_name ?? '-' }}</p>
                <p class="text-sm text-base-content/60">Code: {{ $config->estate_code ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-base-content/50 uppercase font-bold mb-1">Integration</p>
                <p class="font-semibold">{{ $config->integration_type === 1 ? 'SAP' : 'Pinfosys' }}</p>
                <p class="text-sm text-base-content/60">URL: {{ $config->sap_api_url ? 'Configured' : 'Not set' }}</p>
            </div>
            <div>
                <p class="text-xs text-base-content/50 uppercase font-bold mb-1">System Types</p>
                <div class="flex gap-2 flex-wrap">
                    <span class="badge {{ $config->system_is_palm ? 'badge-success' : 'badge-ghost' }}">Palm</span>
                    <span class="badge {{ $config->system_is_coconut ? 'badge-success' : 'badge-ghost' }}">Coconut</span>
                    <span class="badge {{ $config->system_is_durian ? 'badge-success' : 'badge-ghost' }}">Durian</span>
                    <span class="badge {{ $config->system_is_rubber ? 'badge-success' : 'badge-ghost' }}">Rubber</span>
                </div>
            </div>
            <div>
                <p class="text-xs text-base-content/50 uppercase font-bold mb-1">System Status</p>
                <span class="badge {{ $config->is_lock_system ? 'badge-error' : 'badge-success' }}">
                    {{ $config->is_lock_system ? '🔒 Locked' : '🔓 Open' }}
                </span>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-base-200">
            <p class="text-sm text-base-content/50">Full estate settings configuration coming in Sprint 2.</p>
        </div>
        @else
        <p class="text-base-content/50">No configuration found for this company. Please contact Super Admin.</p>
        @endif
    </div>
</div>
@endsection
