{{-- ─────────────────────────────────────────────────────────────────────── --}}
{{-- SIDEBAR                                                                 --}}
{{-- ─────────────────────────────────────────────────────────────────────── --}}
@php
    $roleLevel   = session('role_level', 99);
    $isPalm      = session('is_palm', false);
    $isCoconut   = session('is_coconut', false);
    $isDurian    = session('is_durian', false);
    $currentRoute = request()->route()?->getName() ?? '';
@endphp

<aside class="w-64 min-h-screen bg-base-100 border-r border-base-200 flex flex-col">

    {{-- Logo (visible on mobile drawer) --}}
    <div class="flex items-center gap-2 px-4 py-4 border-b border-base-200 lg:hidden">
        <div class="w-8 h-8 bg-primary rounded flex items-center justify-center">
            <span class="text-primary-content font-bold text-xs">EP</span>
        </div>
        <span class="font-bold text-base">EPMS WEB</span>
    </div>

    {{-- Role Label --}}
    @auth
    <div class="px-4 pt-4 pb-2">
        <p class="text-xs font-bold text-base-content/40 uppercase tracking-widest">
            {{ strtoupper(session('role_name', 'User')) }}
        </p>
    </div>
    @endauth

    {{-- Navigation Menu --}}
    <nav class="flex-1 px-2 py-2 overflow-y-auto">
        <ul class="menu menu-sm gap-0.5 w-full p-0">

            {{-- Dashboard --}}
            <li>
                <a href="{{ route('dashboard') }}"
                   class="{{ str_starts_with($currentRoute, 'dashboard') ? 'active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
            </li>

            {{-- Master Data (collapsible) --}}
            <li x-data="{ open: {{ str_starts_with($currentRoute, 'masters.') ? 'true' : 'false' }} }">
                <details :open="open">
                    <summary @click.prevent="open = !open" class="cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582 4 8 4"/>
                        </svg>
                        Master Data
                        <svg class="ml-auto h-4 w-4 transition-transform" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                        </svg>
                    </summary>
                    <ul x-show="open" x-collapse>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.estate' ? 'active' : '' }}">Estate</a></li>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.division' ? 'active' : '' }}">Division</a></li>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.block' ? 'active' : '' }}">Block</a></li>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.employee' ? 'active' : '' }}">Employee</a></li>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.activity' ? 'active' : '' }}">Activity</a></li>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.material' ? 'active' : '' }}">Material</a></li>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.vendor' ? 'active' : '' }}">Vendor</a></li>
                        <li><a href="#" class="pl-8 text-sm {{ $currentRoute === 'masters.device' ? 'active' : '' }}">Device</a></li>
                        @if($isPalm)
                        <li><a href="#" class="pl-8 text-sm">OPH Card</a></li>
                        <li><a href="#" class="pl-8 text-sm">FDN Card</a></li>
                        @endif
                        @if($isCoconut)
                        <li><a href="#" class="pl-8 text-sm">Coconut Material</a></li>
                        @endif
                        @if($isDurian)
                        <li><a href="#" class="pl-8 text-sm">Durian Variety</a></li>
                        @endif
                    </ul>
                </details>
            </li>

            {{-- Grouping (collapsible) --}}
            <li x-data="{ open: {{ str_starts_with($currentRoute, 'grouping.') ? 'true' : 'false' }} }">
                <details :open="open">
                    <summary @click.prevent="open = !open" class="cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Grouping
                        <svg class="ml-auto h-4 w-4 transition-transform" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                        </svg>
                    </summary>
                    <ul x-show="open" x-collapse>
                        <li><a href="#" class="pl-8 text-sm">Gang Employee</a></li>
                        <li><a href="#" class="pl-8 text-sm">Field Staff</a></li>
                        <li><a href="#" class="pl-8 text-sm">Mandor Employee</a></li>
                        <li><a href="#" class="pl-8 text-sm">Field Assistant Division</a></li>
                    </ul>
                </details>
            </li>

            {{-- Manager Substitution --}}
            <li>
                <a href="#" class="{{ $currentRoute === 'grouping.substitution' ? 'active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Manager Substitution
                </a>
            </li>

            {{-- Account Settings (Company Admin+) --}}
            @if($roleLevel <= 30)
            <li>
                <a href="{{ route('admin.users.index') }}"
                   class="{{ str_starts_with($currentRoute, 'admin.users') ? 'active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Account Settings
                </a>
            </li>

            {{-- Estate Settings --}}
            <li>
                <a href="{{ route('admin.config.index') }}"
                   class="{{ str_starts_with($currentRoute, 'admin.config') ? 'active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9"/>
                    </svg>
                    Estate Settings
                </a>
            </li>
            @endif

            {{-- Activity Log --}}
            <li>
                <a href="#" class="{{ $currentRoute === 'admin.audit' ? 'active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Activity Log
                </a>
            </li>

            {{-- Audit File Generator --}}
            @if($roleLevel <= 30)
            <li>
                <a href="#" class="{{ $currentRoute === 'admin.audit-file' ? 'active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Audit File Generator
                </a>
            </li>
            @endif

        </ul>
    </nav>

    {{-- Footer Info --}}
    <div class="px-4 py-3 border-t border-base-200 text-xs text-base-content/40">
        @auth
        <p class="font-semibold truncate">{{ session('company_code', '') }}</p>
        <p class="truncate">{{ session('estate_code', '') }}</p>
        @endauth
    </div>

</aside>
