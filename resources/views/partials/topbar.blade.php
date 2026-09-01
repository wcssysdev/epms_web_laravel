{{-- ─────────────────────────────────────────────────────────────────────── --}}
{{-- TOPBAR                                                                  --}}
{{-- ─────────────────────────────────────────────────────────────────────── --}}
<div class="navbar bg-base-100 shadow-sm px-4 sticky top-0 z-50 min-h-14">

    {{-- LEFT: Hamburger + Logo --}}
    <div class="flex-none">
        <label for="sidebar-drawer" class="btn btn-ghost btn-circle btn-sm drawer-button lg:hidden">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </label>
    </div>

    <div class="flex-none hidden lg:flex items-center gap-2 px-2">
        <div class="w-8 h-8 bg-primary rounded flex items-center justify-center">
            <span class="text-primary-content font-bold text-xs">EP</span>
        </div>
        <span class="font-bold text-base text-base-content">EPMS WEB</span>
    </div>

    {{-- CENTER: Estate Name --}}
    <div class="flex-1 px-4">
        <div>
            <h1 class="font-bold text-base text-base-content leading-tight">
                EPMS
                @auth
                    <span class="text-primary uppercase">{{ session('estate_name', 'Estate') }}</span>
                @endauth
            </h1>
            <p class="text-xs text-base-content/50">Electronic Plantation Mobile Solution</p>
        </div>
    </div>

    {{-- RIGHT: Badges + Actions + User --}}
    <div class="flex-none flex items-center gap-2">

        @auth
        {{-- Status Badges --}}
        <div class="hidden sm:flex items-center gap-1">
            <span class="badge badge-success badge-sm font-bold px-2 py-1 text-xs"
                  title="SAP Client: {{ session('sap_client', '000') }} | Environment: {{ app()->environment() }} | Version: {{ config('app.version') }}">
                {{ session('sap_client', '000') }}
            </span>
            <span class="badge badge-success badge-sm font-bold px-1 py-0.5 text-xs"
                  title="APP_ENV: {{ app()->environment() }}">
                {{ strtoupper(app()->environment() === 'local' ? 'DEV' : app()->environment()) }}
            </span>
            <span class="badge badge-ghost badge-sm font-bold px-1 py-0.5 text-xs"
                  title="Version: {{ config('app.version') }}">
                v{{ config('app.version') }}
            </span>
        </div>

        {{-- System Lock Indicator --}}
        @if(session('is_locked'))
        <div class="tooltip tooltip-bottom" data-tip="System Locked">
            <span class="badge badge-error badge-sm animate-pulse">🔒 LOCKED</span>
        </div>
        @endif
        @endauth

        {{-- Dark Mode Toggle --}}
        <div x-data="darkMode()" class="flex">
            <button @click="toggle()"
                    class="btn btn-ghost btn-circle btn-sm"
                    :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                <svg x-show="!isDark" class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M5.64 17l-.71.71a1 1 0 001.41 1.41l.71-.71A1 1 0 005.64 17zM5 12a1 1 0 00-1-1H3a1 1 0 000 2h1a1 1 0 001-1zm7-7a1 1 0 001-1V3a1 1 0 00-2 0v1a1 1 0 001 1zM5.64 7.05a1 1 0 00.7.29 1 1 0 00.71-.29 1 1 0 000-1.41l-.71-.71a1 1 0 00-1.41 1.41zm12 .29a1 1 0 00.7-.29l.71-.71a1 1 0 00-1.41-1.41l-.64.71a1 1 0 000 1.41 1 1 0 00.64.29zM21 11h-1a1 1 0 000 2h1a1 1 0 000-2zm-9 8a1 1 0 00-1 1v1a1 1 0 002 0v-1a1 1 0 00-1-1zm6.36-2a1 1 0 00-1.41 1.41l.71.71a1 1 0 001.41 0 1 1 0 000-1.41zM12 6.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11z"/>
                </svg>
                <svg x-show="isDark" class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M21.64 13a1 1 0 00-1.27-.66 8.11 8.11 0 01-2.13.28 8.21 8.21 0 01-8.2-8.2 8.11 8.11 0 01.28-2.13 1 1 0 00-1.27-1.21 10 10 0 1012.59 12.59 1 1 0 00-.66-1.27z"/>
                </svg>
            </button>
        </div>

        {{-- Notification Bell (placeholder) --}}
        <button class="btn btn-ghost btn-circle btn-sm">
            <div class="indicator">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>
        </button>

        @auth
        {{-- User Dropdown --}}
        <div class="dropdown dropdown-end" x-data="{ open: false }">
            <div tabindex="0" role="button" @click="open = !open"
                 class="flex items-center gap-2 cursor-pointer hover:bg-base-200 rounded-btn px-2 py-1 transition-colors">
                <div class="avatar placeholder">
                    <div class="bg-primary text-primary-content rounded-full w-8">
                        <span class="text-sm font-bold">{{ strtoupper(substr(session('user_name', 'U'), 0, 1)) }}</span>
                    </div>
                </div>
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-semibold leading-tight">{{ session('user_name', auth()->user()->user_name) }}</p>
                    <p class="text-xs text-base-content/50 leading-tight">{{ session('role_name', '') }}</p>
                </div>
                <svg class="w-4 h-4 text-base-content/50 transition-transform" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                </svg>
            </div>
            <ul x-show="open" @click.outside="open = false"
                class="dropdown-content z-[1] menu p-2 shadow-lg bg-base-100 rounded-box w-52 border border-base-200 mt-1">
                <li class="menu-title px-2 py-1">
                    <span class="text-xs text-base-content/50">{{ session('company_name', '') }}</span>
                </li>
                <li>
                    <a href="{{ route('change-password') }}" class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        Change Password
                    </a>
                </li>
                <li><hr class="my-1 border-base-200"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 text-error w-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        @endauth

    </div>
</div>
