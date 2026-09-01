<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="appLayout()"
      :data-theme="theme">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <meta name="user-id" content="{{ auth()->id() }}"/>

    <title>@yield('title', 'Dashboard') — EPMS IOI</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌴</text></svg>"/>

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Page-specific styles --}}
    @stack('styles')
</head>

<body class="bg-base-200 min-h-screen">

{{-- ── DRAWER LAYOUT (sidebar + main) ────────────────────────────────────── --}}
<div class="drawer lg:drawer-open">
    <input id="sidebar-drawer" type="checkbox" class="drawer-toggle"/>

    {{-- Main Content Area --}}
    <div class="drawer-content flex flex-col min-h-screen">

        {{-- Topbar --}}
        @include('partials.topbar')

        {{-- Page Content --}}
        <main class="flex-1 p-6">

            {{-- Breadcrumb --}}
            @hasSection('breadcrumb')
            <div class="text-sm breadcrumbs mb-4">
                <ul>
                    <li><a href="{{ route('dashboard') }}">Home</a></li>
                    @yield('breadcrumb')
                </ul>
            </div>
            @endif

            {{-- Flash Messages --}}
            @include('partials.flash-messages')

            {{-- Page Title --}}
            @hasSection('page-title')
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-base-content">@yield('page-title')</h1>
                    @hasSection('page-subtitle')
                    <p class="text-base-content/50 text-sm mt-0.5">@yield('page-subtitle')</p>
                    @endif
                </div>
                @hasSection('page-actions')
                <div class="flex items-center gap-2">
                    @yield('page-actions')
                </div>
                @endif
            </div>
            @endif

            {{-- Main Slot --}}
            @yield('content')

        </main>

        {{-- Footer --}}
        <footer class="footer footer-center p-3 bg-base-100 border-t border-base-200 text-xs text-base-content/40">
            <p>EPMS IOI v{{ config('app.version') }} &copy; {{ date('Y') }}</p>
        </footer>

    </div>

    {{-- Sidebar Drawer --}}
    <div class="drawer-side z-40">
        <label for="sidebar-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
        @include('partials.sidebar')
    </div>
</div>

{{-- ── ALPINE.JS GLOBAL SCRIPTS ───────────────────────────────────────────── --}}
<script>
    // Dark mode component
    function darkMode() {
        return {
            isDark: localStorage.getItem('theme') === 'dark',
            toggle() {
                this.isDark = !this.isDark;
                const theme = this.isDark ? 'dark' : 'light';
                localStorage.setItem('theme', theme);
                document.documentElement.setAttribute('data-theme', theme);
            }
        }
    }

    // App layout component
    function appLayout() {
        const saved = localStorage.getItem('theme') || 'light';
        return {
            theme: saved,
            init() {
                // Apply saved theme immediately
                document.documentElement.setAttribute('data-theme', this.theme);
            }
        }
    }

    // Axios CSRF setup for AJAX calls
    window.axios && (window.axios.defaults.headers.common['X-CSRF-TOKEN'] =
        document.querySelector('meta[name="csrf-token"]')?.content);
</script>

{{-- Page-specific scripts --}}
@stack('scripts')

</body>
</html>
