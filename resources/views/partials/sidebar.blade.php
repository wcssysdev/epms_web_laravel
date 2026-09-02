@php
    $roleLevel   = session('role_level', 99);
    $isPalm      = session('is_palm', false);
    $isCoconut   = session('is_coconut', false);
    $isDurian    = session('is_durian', false);
    $route       = request()->route()?->getName() ?? '';
@endphp

<aside class="fixed left-0 top-0 z-40 h-screen max-w-[290px] overflow-hidden border-r border-gray-200 bg-white transition-[width] duration-200 ease-linear dark:border-gray-800 dark:bg-gray-dark w-full"
       :class="sidebarOpen ? 'w-full' : 'w-0'"
       aria-label="Main navigation">

    <div class="flex h-full flex-col py-10 pl-[25px] pr-[7px]">

        {{-- Logo --}}
        <div class="relative pr-4">
            <a href="{{ route('dashboard') }}" class="block py-2.5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded bg-primary text-white text-xs font-bold shrink-0">
                        🌴
                    </div>
                    <span class="text-2xl font-bold sidebar-logo-text">EPMS WEB</span>
                </div>
            </a>
        </div>

        {{-- Scrollable nav --}}
        <div class="custom-scrollbar mt-10 flex-1 overflow-y-auto pr-3">

            {{-- ── MAIN section ─────────────────────────────────────────── --}}
            <div class="mb-6">
                <h2 class="mb-5 text-sm font-medium uppercase tracking-widest sidebar-section-title">
                    {{ strtoupper(session('role_name', 'User')) }}
                </h2>
                <nav>
                    <ul class="space-y-2">
                        {{-- Dashboard --}}
                        <li>
                            <a href="{{ route('dashboard') }}"
                               class="sidebar-item {{ in_array($route,['dashboard','home']) ? 'active' : '' }}">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M9 17.25a.75.75 0 000 1.5h6a.75.75 0 000-1.5H9z"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.25c-.725 0-1.387.2-2.11.537-.702.327-1.512.81-2.528 1.415l-1.456.867c-1.119.667-2.01 1.198-2.686 1.706C2.523 6.3 2 6.84 1.66 7.551c-.342.711-.434 1.456-.405 2.325.029.841.176 1.864.36 3.146l.293 2.032c.237 1.65.426 2.959.707 3.978.29 1.05.702 1.885 1.445 2.524.742.64 1.63.925 2.716 1.062 1.056.132 2.387.132 4.066.132h2.316c1.68 0 3.01 0 4.066-.132 1.086-.137 1.974-.422 2.716-1.061.743-.64 1.155-1.474 1.445-2.525.281-1.02.47-2.328.707-3.978l.292-2.032c.185-1.282.332-2.305.36-3.146.03-.87-.062-1.614-.403-2.325C22 6.84 21.477 6.3 20.78 5.775c-.675-.508-1.567-1.039-2.686-1.706l-1.456-.867c-1.016-.605-1.826-1.088-2.527-1.415-.724-.338-1.386-.537-2.111-.537z"/>
                                </svg>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

            {{-- ── Master Data (collapsible) ────────────────────────────── --}}
            <div class="mb-6" x-data="{ open: {{ str_starts_with($route,'masters.') ? 'true':'false' }} }">
                <nav>
                    <ul class="space-y-2">
                        <li>
                            <button @click="open = !open" :aria-expanded="open.toString()"
                                    class="sidebar-item w-full text-left">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.5 6 2s-2.13 2-6 2-6-1.5-6-2 2.13-2 6-2zm6 12c0 .5-2.13 2-6 2s-6-1.5-6-2v-2.23c1.61.78 3.72 1.23 6 1.23s4.39-.45 6-1.23V17zm0-5c0 .5-2.13 2-6 2s-6-1.5-6-2V9.77C7.61 10.55 9.72 11 12 11s4.39-.45 6-1.23V12z"/>
                                </svg>
                                <span>Master Data</span>
                                <svg width="16" height="8" viewBox="0 0 16 8" fill="currentColor"
                                     class="ml-auto transition-transform duration-200"
                                     :class="open ? 'rotate-0' : 'rotate-180'">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.553.728a.687.687 0 01.895 0l6.416 5.5a.688.688 0 01-.895 1.044L8 2.155 2.03 7.272a.688.688 0 11-.894-1.044l6.417-5.5z"/>
                                </svg>
                            </button>
                            <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-10">
                                @foreach([
                                    'Estate','Division','Block','Employee',
                                    'Activity','Material','Vendor','Device',
                                ] as $label)
                                <li>
                                    <a href="#" class="sidebar-subitem">{{ $label }}</a>
                                </li>
                                @endforeach
                                @if($isPalm)
                                <li><a href="#" class="sidebar-subitem">OPH Card</a></li>
                                <li><a href="#" class="sidebar-subitem">FDN Card</a></li>
                                @endif
                                @if($isCoconut)
                                <li><a href="#" class="sidebar-subitem">Coconut Material</a></li>
                                @endif
                                @if($isDurian)
                                <li><a href="#" class="sidebar-subitem">Durian Variety</a></li>
                                @endif
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>

            {{-- ── Grouping (collapsible) ───────────────────────────────── --}}
            <div class="mb-6" x-data="{ open: {{ str_starts_with($route,'grouping.') ? 'true':'false' }} }">
                <nav>
                    <ul class="space-y-2">
                        <li>
                            <button @click="open = !open" :aria-expanded="open.toString()"
                                    class="sidebar-item w-full text-left">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                </svg>
                                <span>Grouping</span>
                                <svg width="16" height="8" viewBox="0 0 16 8" fill="currentColor"
                                     class="ml-auto transition-transform duration-200"
                                     :class="open ? 'rotate-0' : 'rotate-180'">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.553.728a.687.687 0 01.895 0l6.416 5.5a.688.688 0 01-.895 1.044L8 2.155 2.03 7.272a.688.688 0 11-.894-1.044l6.417-5.5z"/>
                                </svg>
                            </button>
                            <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-10">
                                <li><a href="#" class="sidebar-subitem">Gang Employee</a></li>
                                <li><a href="#" class="sidebar-subitem">Field Staff</a></li>
                                <li><a href="#" class="sidebar-subitem">Mandor Employee</a></li>
                                <li><a href="#" class="sidebar-subitem">Field Assistant Division</a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>

            {{-- ── Standalone items ─────────────────────────────────────── --}}
            <div class="mb-6">
                <nav>
                    <ul class="space-y-2">

                        {{-- Manager Substitution --}}
                        <li>
                            <a href="#" class="sidebar-item {{ $route === 'grouping.substitution' ? 'active' : '' }}">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                </svg>
                                <span>Manager Substitution</span>
                            </a>
                        </li>

                        {{-- Account Settings (admin+) --}}
                        @if($roleLevel <= 30)
                        <li>
                            <a href="{{ route('admin.users.index') }}"
                               class="sidebar-item {{ str_starts_with($route,'admin.users') ? 'active' : '' }}">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                <span>Account Settings</span>
                            </a>
                        </li>

                        {{-- Estate Settings --}}
                        <li>
                            <a href="{{ route('admin.config.index') }}"
                               class="sidebar-item {{ str_starts_with($route,'admin.config') ? 'active' : '' }}">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M9 17.25a.75.75 0 000 1.5h6a.75.75 0 000-1.5H9z"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.25c-.725 0-1.387.2-2.11.537-.702.327-1.512.81-2.528 1.415l-1.456.867c-1.119.667-2.01 1.198-2.686 1.706C2.523 6.3 2 6.84 1.66 7.551c-.342.711-.434 1.456-.405 2.325.029.841.176 1.864.36 3.146l.293 2.032c.237 1.65.426 2.959.707 3.978.29 1.05.702 1.885 1.445 2.524.742.64 1.63.925 2.716 1.062 1.056.132 2.387.132 4.066.132h2.316c1.68 0 3.01 0 4.066-.132 1.086-.137 1.974-.422 2.716-1.061.743-.64 1.155-1.474 1.445-2.525.281-1.02.47-2.328.707-3.978l.292-2.032c.185-1.282.332-2.305.36-3.146.03-.87-.062-1.614-.403-2.325C22 6.84 21.477 6.3 20.78 5.775c-.675-.508-1.567-1.039-2.686-1.706l-1.456-.867c-1.016-.605-1.826-1.088-2.527-1.415-.724-.338-1.386-.537-2.111-.537z"/>
                                </svg>
                                <span>Estate Settings</span>
                            </a>
                        </li>
                        @endif

                        {{-- Activity Log --}}
                        <li>
                            <a href="#" class="sidebar-item {{ $route === 'admin.audit' ? 'active' : '' }}">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                </svg>
                                <span>Activity Log</span>
                            </a>
                        </li>

                        @if($roleLevel <= 30)
                        {{-- Audit File Generator --}}
                        <li>
                            <a href="#" class="sidebar-item {{ $route === 'admin.audit-file' ? 'active' : '' }}">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                </svg>
                                <span>Audit File Generator</span>
                            </a>
                        </li>

                        {{-- Retrieve Master Data --}}
                        <li>
                            <a href="#" class="sidebar-item">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                </svg>
                                <span>Retrieve Master Data</span>
                            </a>
                        </li>

                        {{-- Delete Pictures --}}
                        <li>
                            <a href="#" class="sidebar-item">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                <span>Delete Pictures</span>
                            </a>
                        </li>
                        @endif

                    </ul>
                </nav>
            </div>

            {{-- ── HELP section ──────────────────────────────────────────── --}}
            <div class="mb-6">
                <h2 class="mb-5 text-sm font-medium uppercase tracking-widest sidebar-section-title">HELP</h2>
                <nav>
                    <ul class="space-y-2">
                        <li>
                            <a href="#" class="sidebar-item">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                                    <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-2 14H7v-2h3v2zm0-4H7v-2h3v2zm0-4H7V7h3v2zm7 8h-5v-2h5v2zm0-4h-5v-2h5v2zm0-4h-5V7h5v2z"/>
                                </svg>
                                <span>User Guide</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

        </div>
    </div>
</aside>
