<header class="sticky top-0 z-30 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-5 shadow-sm dark:border-gray-800 dark:bg-gray-dark md:px-5 2xl:px-10">

    {{-- LEFT: Toggle + Title --}}
    <div class="flex items-center gap-4">
        {{-- Sidebar toggle button --}}
        <button @click="sidebarOpen = !sidebarOpen"
                class="rounded-lg border border-gray-200 px-2 py-2 transition-colors hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-[#FFFFFF1A]">
            <svg width="25" height="24" viewBox="0 0 25 24" fill="currentColor">
                <path d="M3.5625 6C3.5625 5.58579 3.89829 5.25 4.3125 5.25H20.3125C20.7267 5.25 21.0625 5.58579 21.0625 6C21.0625 6.41421 20.7267 6.75 20.3125 6.75L4.3125 6.75C3.89829 6.75 3.5625 6.41422 3.5625 6Z"/>
                <path d="M3.5625 18C3.5625 17.5858 3.89829 17.25 4.3125 17.25L20.3125 17.25C20.7267 17.25 21.0625 17.5858 21.0625 18C21.0625 18.4142 20.7267 18.75 20.3125 18.75L4.3125 18.75C3.89829 18.75 3.5625 18.4142 3.5625 18Z"/>
                <path d="M4.3125 11.25C3.89829 11.25 3.5625 11.5858 3.5625 12C3.5625 12.4142 3.89829 12.75 4.3125 12.75L20.3125 12.75C20.7267 12.75 21.0625 12.4142 21.0625 12C21.0625 11.5858 20.7267 11.25 20.3125 11.25L4.3125 11.25Z"/>
            </svg>
            <span class="sr-only">Toggle Sidebar</span>
        </button>

        {{-- Estate Title --}}
        <div class="hidden xl:block">
            <h1 class="mb-0.5 text-xl font-bold text-gray-900 dark:text-white">
                EPMS <span class="text-primary uppercase">{{ session('estate_name', 'Estate') }}</span>
            </h1>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Electronic Plantation Mobile Solution
                <span class="ml-1 text-xs font-normal text-gray-400">v{{ config('app.version') }}</span>
            </p>
        </div>
    </div>

    {{-- RIGHT: Badges + Dark mode + Bell + User --}}
    <div class="flex flex-1 items-center justify-end gap-2 min-[375px]:gap-4">

        @auth
        {{-- Status Badges --}}
        <div class="flex items-center gap-1">
            <span class="rounded px-2 py-1 text-xs font-bold bg-green-500 text-white"
                  title="SAP Client: {{ session('sap_client','000') }} | Environment: {{ app()->environment() }} | Version: {{ config('app.version') }}">
                {{ session('sap_client', '000') }}
            </span>
            <span class="rounded px-1 py-0.5 text-xs font-bold bg-green-600 text-white"
                  title="APP_ENV: {{ app()->environment() }}">
                {{ strtoupper(app()->environment() === 'local' ? 'DEV' : app()->environment()) }}
            </span>
            <span class="rounded px-1 py-0.5 text-xs font-bold bg-gray-500 text-white"
                  title="Version: {{ config('app.version') }}">
                v{{ config('app.version') }}
            </span>
        </div>

        {{-- System Lock Indicator --}}
        @if(session('is_locked'))
        <span class="rounded px-2 py-1 text-xs font-bold bg-red-500 text-white animate-pulse">🔒 LOCKED</span>
        @endif
        @endauth

        {{-- Dark Mode Toggle (pill style) --}}
        <button x-on:click="toggleDark()"
                class="group rounded-full bg-gray-100 p-[5px] text-gray-800 dark:bg-gray-800 dark:text-white outline-none"
                title="Toggle dark mode">
            <span class="relative flex gap-2.5" aria-hidden="true">
                <span class="absolute size-[38px] rounded-full border border-gray-200 bg-white transition-all dark:translate-x-[48px] dark:border-none dark:bg-gray-700"></span>
                {{-- Sun icon --}}
                <span class="relative grid size-[38px] place-items-center rounded-full">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 1.042c.345 0 .625.28.625.625V2.5a.625.625 0 11-1.25 0v-.833c0-.346.28-.625.625-.625zM3.666 3.665a.625.625 0 01.883 0l.328.328a.625.625 0 01-.884.884l-.327-.328a.625.625 0 010-.884zm12.668 0a.625.625 0 010 .884l-.327.328a.625.625 0 01-.884-.884l.327-.327a.625.625 0 01.884 0zM10 5.626a4.375 4.375 0 100 8.75 4.375 4.375 0 000-8.75zM4.375 10a5.625 5.625 0 1111.25 0 5.625 5.625 0 01-11.25 0zm-3.333 0c0-.345.28-.625.625-.625H2.5a.625.625 0 110 1.25h-.833A.625.625 0 011.042 10zm15.833 0c0-.345.28-.625.625-.625h.833a.625.625 0 010 1.25H17.5a.625.625 0 01-.625-.625zm-1.752 5.123a.625.625 0 01.884 0l.327.327a.625.625 0 11-.884.884l-.327-.327a.625.625 0 010-.884zm-10.246 0a.625.625 0 010 .884l-.328.327a.625.625 0 11-.883-.884l.327-.327a.625.625 0 01.884 0zM10 16.875c.345 0 .625.28.625.625v.833a.625.625 0 01-1.25 0V17.5c0-.345.28-.625.625-.625z"/>
                    </svg>
                </span>
                {{-- Moon icon --}}
                <span class="relative grid size-[38px] place-items-center rounded-full dark:text-white">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.18 2.334a7.71 7.71 0 108.485 8.485A6.042 6.042 0 119.18 2.335zM1.042 10a8.958 8.958 0 018.958-8.958c.598 0 .896.476.948.855.049.364-.086.828-.505 1.082a4.792 4.792 0 106.579 6.579c.253-.42.717-.555 1.081-.506.38.052.856.35.856.948A8.958 8.958 0 011.04 10z"/>
                    </svg>
                </span>
            </span>
        </button>

        {{-- Notification Bell --}}
        <div class="relative">
            <button class="relative grid size-12 place-items-center rounded-full border border-gray-200 bg-gray-50 text-gray-700 hover:text-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10 1.042A6.458 6.458 0 003.542 7.5v.587c0 .58-.172 1.148-.495 1.631l-.957 1.436a2.934 2.934 0 001.67 4.459c.63.171 1.264.316 1.903.435l.002.005c.64 1.71 2.353 2.905 4.335 2.905 1.982 0 3.694-1.196 4.335-2.905l.002-.005a23.736 23.736 0 001.903-.435 2.934 2.934 0 001.67-4.459l-.958-1.436a2.941 2.941 0 01-.494-1.631V7.5A6.458 6.458 0 0010 1.042zm2.813 15.239a23.71 23.71 0 01-5.627 0c.593.85 1.623 1.427 2.814 1.427 1.19 0 2.221-.576 2.813-1.427zM4.792 7.5a5.208 5.208 0 1110.416 0v.587c0 .827.245 1.636.704 2.325l.957 1.435c.638.957.151 2.257-.958 2.56a22.467 22.467 0 01-11.822 0 1.684 1.684 0 01-.959-2.56l.958-1.435a4.192 4.192 0 00.704-2.325V7.5z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        @auth
        {{-- User Dropdown --}}
        <div class="shrink-0" x-data="{ open: false }">
            <div class="relative">
                <button @click="open = !open" @click.outside="open = false"
                        class="rounded align-middle outline-none">
                    <span class="sr-only">My Account</span>
                    <figure class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-white font-semibold text-lg">
                            {{ strtoupper(substr(session('user_name', 'U'), 0, 1)) }}
                        </div>
                        <figcaption class="flex items-center gap-1 font-medium text-gray-800 dark:text-gray-300 max-[1024px]:hidden">
                            <span>{{ session('user_name', auth()->user()->user_name) }}</span>
                            <svg width="22" height="22" viewBox="0 0 22 22" fill="currentColor"
                                 class="transition-transform" :class="open ? 'rotate-0' : 'rotate-180'">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10.551 7.728a.687.687 0 01.895 0l6.417 5.5a.687.687 0 11-.895 1.044l-5.97-5.117-5.969 5.117a.687.687 0 01-.894-1.044l6.416-5.5z"/>
                            </svg>
                        </figcaption>
                    </figure>
                </button>

                {{-- Dropdown menu --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-52 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800 z-50">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 truncate">{{ session('company_name', '') }}</p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white truncate">{{ session('user_name', '') }}</p>
                    </div>
                    <ul class="py-2">
                        <li>
                            <a href="{{ route('change-password') }}"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                Change Password
                            </a>
                        </li>
                        <li class="border-t border-gray-200 dark:border-gray-700 mt-1 pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        @endauth

    </div>
</header>
