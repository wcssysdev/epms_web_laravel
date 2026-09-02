<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="appLayout()"
      :class="{ 'dark': isDark }">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>@yield('title', 'Dashboard') — EPMS IOI</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌴</text></svg>"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="bg-gray-100 dark:bg-[#020d1a] min-h-screen">

<div class="min-h-screen">

    {{-- ── SIDEBAR (fixed) ──────────────────────────────────────────────── --}}
    @include('partials.sidebar')

    {{-- ── MAIN CONTENT (offset by sidebar width) ──────────────────────── --}}
    <div class="transition-all duration-200 ease-linear"
         :class="sidebarOpen ? 'ml-[290px]' : 'ml-0'">

        {{-- Topbar --}}
        @include('partials.topbar')

        {{-- Page Content --}}
        <main class="mx-auto w-full overflow-auto p-4 md:p-6 2xl:p-10 max-w-screen-2xl">

            {{-- Breadcrumb --}}
            @hasSection('breadcrumb')
            <nav class="mb-4">
                <ol class="flex items-center gap-2 text-sm">
                    <li><a class="font-medium text-gray-500 hover:text-primary" href="{{ route('dashboard') }}">Home /</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
            @endif

            {{-- Page Header --}}
            @hasSection('page-title')
            <div class="mb-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">@yield('page-title')</h1>
                        @hasSection('page-subtitle')
                        <p class="mt-1 text-gray-500 dark:text-gray-400">@yield('page-subtitle')</p>
                        @endif
                    </div>
                    @hasSection('page-actions')
                    <div class="flex items-center gap-2">@yield('page-actions')</div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Flash Messages --}}
            @include('partials.flash-messages')

            {{-- Main Slot --}}
            @yield('content')

        </main>

        {{-- Footer --}}
        <footer class="border-t border-gray-200 bg-white px-6 py-3 text-center text-xs text-gray-400 dark:border-gray-800 dark:bg-gray-dark">
            EPMS IOI v{{ config('app.version') }} &copy; {{ date('Y') }}
        </footer>

    </div>
</div>

<script>
    function appLayout() {
        // Default: light, kecuali user sudah pernah pilih dark
        const saved = localStorage.getItem('theme');
        const isDark = saved === 'dark'; // TIDAK pakai prefers-color-scheme
        return {
            isDark: isDark,
            sidebarOpen: window.innerWidth >= 1024,
            toggleDark() {
                this.isDark = !this.isDark;
                localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
            },
            init() {
                window.addEventListener('resize', () => {
                    if (window.innerWidth < 1024) this.sidebarOpen = false;
                    else this.sidebarOpen = true;
                });
            }
        }
    }
</script>

@stack('scripts')
</body>
</html>
