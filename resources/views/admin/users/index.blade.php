@extends('layouts.app')
@section('title', 'Account Settings')
@section('page-title', 'Account Settings')
@section('page-subtitle', 'Manage user accounts and access levels')
@section('breadcrumb')
    <li>Account Settings</li>
@endsection
@section('content')
<div class="card bg-base-100 shadow-sm">
    <div class="card-body">
        <div class="flex items-center gap-3 text-base-content/50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            </svg>
            <div>
                <p class="font-semibold text-base-content">Account Settings</p>
                <p class="text-sm">Coming in Sprint 1 — User management, roles, and permissions.</p>
            </div>
        </div>
    </div>
</div>
@endsection
