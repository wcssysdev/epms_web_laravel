<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>EPMS IOI — Stack Test</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-base-200 min-h-screen">

    {{-- NAVBAR --}}
    <div class="navbar bg-base-100 shadow-sm px-6">
        <div class="flex-1">
            <span class="font-bold text-lg text-primary">EPMS WEB</span>
        </div>
        <div class="flex items-center gap-2">
            {{-- Badge SAP client, env, version (like screenshot) --}}
            <div class="flex items-center gap-1">
                <span class="badge badge-success font-bold text-xs" title="SAP Client">100</span>
                <span class="badge badge-success badge-sm font-bold text-xs" title="APP_ENV">{{ strtoupper(app()->environment()) }}</span>
                <span class="badge badge-ghost badge-sm font-bold text-xs" title="Version">v{{ config('app.version') }}</span>
            </div>
            {{-- Dark mode toggle --}}
            <label class="swap swap-rotate btn btn-ghost btn-circle btn-sm" x-data @click="
                const html = document.documentElement;
                const current = html.getAttribute('data-theme');
                html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
            ">
                <svg class="swap-off w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M5.64 17l-.71.71a1 1 0 0 0 1.41 1.41l.71-.71A1 1 0 0 0 5.64 17zM5 12a1 1 0 0 0-1-1H3a1 1 0 0 0 0 2h1a1 1 0 0 0 1-1zm7-7a1 1 0 0 0 1-1V3a1 1 0 0 0-2 0v1a1 1 0 0 0 1 1zM5.64 7.05a1 1 0 0 0 .7.29 1 1 0 0 0 .71-.29 1 1 0 0 0 0-1.41l-.71-.71a1 1 0 0 0-1.41 1.41zm12 .29a1 1 0 0 0 .7-.29l.71-.71a1 1 0 0 0-1.41-1.41l-.64.71a1 1 0 0 0 0 1.41 1 1 0 0 0 .64.29zM21 11h-1a1 1 0 0 0 0 2h1a1 1 0 0 0 0-2zm-9 8a1 1 0 0 0-1 1v1a1 1 0 0 0 2 0v-1a1 1 0 0 0-1-1zm6.36-2a1 1 0 0 0-1.41 1.41l.71.71a1 1 0 0 0 1.41 0 1 1 0 0 0 0-1.41zM12 6.5a5.5 5.5 0 1 0 5.5 5.5A5.51 5.51 0 0 0 12 6.5zm0 9a3.5 3.5 0 1 1 3.5-3.5 3.5 3.5 0 0 1-3.5 3.5z"/>
                </svg>
                <svg class="swap-on w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M21.64 13a1 1 0 0 0-1.27-.66 8.11 8.11 0 0 1-2.13.28 8.21 8.21 0 0 1-8.2-8.2 8.11 8.11 0 0 1 .28-2.13 1 1 0 0 0-1.27-1.21 10 10 0 1 0 12.59 12.59 1 1 0 0 0-.66-1.27zm-3.73 5.19a8 8 0 1 1-8.56-12.09 10.23 10.23 0 0 0-.14 1.64 10.22 10.22 0 0 0 10.22 10.22 10.23 10.23 0 0 0 1.64-.14 8 8 0 0 1-3.16.37z"/>
                </svg>
            </label>
            <div class="avatar placeholder">
                <div class="bg-primary text-primary-content w-9 rounded-full">
                    <span class="text-sm font-bold">A</span>
                </div>
            </div>
            <span class="text-sm font-medium">Admin</span>
        </div>
    </div>

    <div class="container mx-auto px-6 py-8">

        <h1 class="text-2xl font-bold mb-2">Stack Installation Test</h1>
        <p class="text-base-content/60 mb-8">Verify all components are working correctly</p>

        {{-- Status Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

            {{-- Laravel --}}
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Framework</h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-success badge-sm">✓</span>
                        <span class="font-semibold">Laravel {{ app()->version() }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-success badge-sm">✓</span>
                        <span class="font-semibold">PHP {{ PHP_VERSION }}</span>
                    </div>
                </div>
            </div>

            {{-- Database --}}
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Database</h3>
                    @php
                        try {
                            $tables = \DB::select("SELECT count(*) as total FROM pg_tables WHERE schemaname='public'");
                            $tableCount = $tables[0]->total;
                            $dbOk = true;
                        } catch (\Exception $e) {
                            $dbOk = false;
                            $tableCount = 0;
                        }
                    @endphp
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge {{ $dbOk ? 'badge-success' : 'badge-error' }} badge-sm">{{ $dbOk ? '✓' : '✗' }}</span>
                        <span class="font-semibold">PostgreSQL 15 — epms_l</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-success badge-sm">✓</span>
                        <span class="font-semibold">{{ $tableCount }} tables</span>
                    </div>
                </div>
            </div>

            {{-- Frontend Stack --}}
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Frontend</h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-success badge-sm">✓</span>
                        <span class="font-semibold">Tailwind CSS v4</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-success badge-sm">✓</span>
                        <span class="font-semibold">daisyUI 5 (if you can read this)</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1" x-data="{ ok: true }">
                        <span class="badge badge-success badge-sm" x-show="ok">✓</span>
                        <span class="font-semibold" x-show="ok">Alpine.js v3 (if you can read this)</span>
                    </div>
                </div>
            </div>

            {{-- PHP Packages --}}
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">PHP Packages</h3>
                    @php
                        $packages = [
                            'Yajra DataTables'  => class_exists(\Yajra\DataTables\DataTables::class),
                            'Maatwebsite Excel' => class_exists(\Maatwebsite\Excel\Excel::class),
                            'Intervention Image'=> class_exists(\Intervention\Image\ImageManager::class),
                            'Simple QR Code'    => class_exists(\SimpleSoftwareIO\QrCode\Generator::class),
                        ];
                    @endphp
                    @foreach($packages as $name => $loaded)
                        <div class="flex items-center gap-2 mt-1">
                            <span class="badge {{ $loaded ? 'badge-success' : 'badge-error' }} badge-sm">{{ $loaded ? '✓' : '✗' }}</span>
                            <span class="font-semibold text-sm">{{ $name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- daisyUI Components Test --}}
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">daisyUI Components</h3>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="badge badge-primary">primary</span>
                        <span class="badge badge-secondary">secondary</span>
                        <span class="badge badge-success">success</span>
                        <span class="badge badge-warning">warning</span>
                        <span class="badge badge-error">error</span>
                    </div>
                    <div class="flex gap-2 mt-2">
                        <button class="btn btn-primary btn-xs">Button</button>
                        <button class="btn btn-ghost btn-xs">Ghost</button>
                    </div>
                </div>
            </div>

            {{-- Alpine.js Interactive Test --}}
            <div class="card bg-base-100 shadow-sm" x-data="{ count: 0, show: false }">
                <div class="card-body p-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Alpine.js Interactive</h3>
                    <div class="flex items-center gap-3 mt-2">
                        <button class="btn btn-sm btn-outline" @click="count--">-</button>
                        <span class="font-bold text-lg w-8 text-center" x-text="count">0</span>
                        <button class="btn btn-sm btn-primary" @click="count++">+</button>
                    </div>
                    <button class="btn btn-ghost btn-xs mt-2" @click="show = !show">Toggle message</button>
                    <div x-show="show" x-collapse class="alert alert-success mt-2 py-2 text-sm">
                        Alpine.js + collapse plugin working! 🎉
                    </div>
                </div>
            </div>

        </div>

        {{-- Quick Access Cards (like screenshot) --}}
        <h2 class="text-lg font-bold mb-4">Quick Access Preview</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach([
                ['icon' => '📁', 'title' => 'Master Data', 'desc' => 'Manage master data: activities, blocks, divisions, employees', 'bg' => 'bg-yellow-50'],
                ['icon' => '🏠', 'title' => 'Estate Settings', 'desc' => 'Configure estate, integration type, and system settings', 'bg' => 'bg-green-50'],
                ['icon' => '👤', 'title' => 'Account Settings', 'desc' => 'Manage user accounts and access levels', 'bg' => 'bg-blue-50'],
                ['icon' => '👥', 'title' => 'Gang Employee', 'desc' => 'Manage gang employee groupings', 'bg' => 'bg-purple-50'],
            ] as $card)
            <div class="card {{ $card['bg'] }} shadow-sm hover:shadow-md transition-shadow cursor-pointer">
                <div class="card-body p-4">
                    <div class="text-3xl mb-2">{{ $card['icon'] }}</div>
                    <h3 class="font-bold text-sm">{{ $card['title'] }}</h3>
                    <p class="text-xs text-base-content/60 mt-1">{{ $card['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>

    </div>

</body>
</html>
