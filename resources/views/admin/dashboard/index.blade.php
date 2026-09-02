@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-title', session('role_name', 'Dashboard'))
@section('page-subtitle', 'Welcome back, ' . session('user_name', 'User'))

@section('content')

{{-- ── STATS ROW ─────────────────────────────────────────────────────────── --}}
@if(!empty($stats))
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <div class="stat bg-base-100 rounded-box shadow-sm p-4">
        <div class="stat-figure text-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div class="stat-title text-xs">Employees</div>
        <div class="stat-value text-2xl text-primary">{{ number_format($stats['employees']) }}</div>
    </div>

    <div class="stat bg-base-100 rounded-box shadow-sm p-4">
        <div class="stat-figure text-success">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9"/>
            </svg>
        </div>
        <div class="stat-title text-xs">Estates</div>
        <div class="stat-value text-2xl text-success">{{ number_format($stats['estates']) }}</div>
    </div>

    <div class="stat bg-base-100 rounded-box shadow-sm p-4">
        <div class="stat-figure text-warning">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
        </div>
        <div class="stat-title text-xs">Divisions</div>
        <div class="stat-value text-2xl text-warning">{{ number_format($stats['divisions']) }}</div>
    </div>

    <div class="stat bg-base-100 rounded-box shadow-sm p-4">
        <div class="stat-figure text-info">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
        </div>
        <div class="stat-title text-xs">Devices</div>
        <div class="stat-value text-2xl text-info">{{ number_format($stats['devices']) }}</div>
    </div>

</div>
@endif

{{-- ── QUICK ACCESS ─────────────────────────────────────────────────────── --}}
<div class="mb-2">
    <h2 class="text-base font-bold text-base-content">Quick Access</h2>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    @foreach($quickAccess as $card)
    @php
        $href = ($card['route'] !== '#' && \Illuminate\Support\Facades\Route::has($card['route']))
            ? route($card['route'])
            : '#';
    @endphp
    <a href="{{ $href }}"
       class="card {{ $card['color'] }} shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 cursor-pointer group">
        <div class="card-body p-4">
            <div class="text-3xl mb-2 group-hover:scale-110 transition-transform">{{ $card['icon'] }}</div>
            <h3 class="card-title text-sm font-bold text-base-content leading-tight">{{ $card['title'] }}</h3>
            <p class="text-xs text-base-content/60 mt-1 leading-relaxed">{{ $card['desc'] }}</p>
        </div>
    </a>
    @endforeach
</div>

{{-- ── SYSTEM STATUS (Admin only) ──────────────────────────────────────── --}}
@if($roleLevel <= 30)
<div class="mt-6">
    <h2 class="text-base font-bold text-base-content mb-2">System Status</h2>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-4">
            <div class="flex flex-wrap gap-4">

                <div class="flex items-center gap-2">
                    <div class="badge {{ session('is_palm') ? 'badge-success' : 'badge-ghost' }} gap-1">
                        <span>🌴</span> Palm
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="badge {{ session('is_coconut') ? 'badge-success' : 'badge-ghost' }} gap-1">
                        <span>🥥</span> Coconut
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="badge {{ session('is_durian') ? 'badge-success' : 'badge-ghost' }} gap-1">
                        <span>🟡</span> Durian
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="badge {{ session('is_rubber') ? 'badge-success' : 'badge-ghost' }} gap-1">
                        <span>🌿</span> Rubber
                    </div>
                </div>

                <div class="divider divider-horizontal mx-0"></div>

                <div class="flex items-center gap-2">
                    <div class="badge {{ session('is_locked') ? 'badge-error' : 'badge-success' }} gap-1">
                        {{ session('is_locked') ? '🔒 System Locked' : '🔓 System Open' }}
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endif

@endsection
