<!DOCTYPE html>
<html lang="en" data-theme="light" x-data="loginPage()" :data-theme="theme">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>Login — EPMS IOI</title>
    <script>
        // Apply saved theme BEFORE CSS loads — prevent flash
        (function() {
            var t = localStorage.getItem('theme');
            // Default is LIGHT — only go dark if explicitly saved
            document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-base-200 flex items-center justify-center p-4">

<div class="w-full max-w-md">

    {{-- Card --}}
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body p-8">

            {{-- Logo + Title --}}
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4 shadow-lg">
                    <span class="text-primary-content font-bold text-2xl">🌴</span>
                </div>
                <h1 class="text-2xl font-bold text-base-content">EPMS IOI</h1>
                <p class="text-base-content/50 text-sm mt-1">Electronic Plantation Mobile Solution</p>
            </div>

            {{-- Flash Error --}}
            @if(session('error'))
            <div class="alert alert-error mb-4 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            @endif

            {{-- Login Form --}}
            <form method="POST" action="{{ route('login.post') }}" @submit="loading = true">
                @csrf

                {{-- Username --}}
                <div class="form-control mb-4">
                    <label class="label pb-1">
                        <span class="label-text font-medium">Username</span>
                    </label>
                    <label class="input input-bordered flex items-center gap-2 {{ $errors->has('username') ? 'input-error' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                        </svg>
                        <input type="text"
                               name="username"
                               placeholder="Enter your username"
                               value="{{ old('username') }}"
                               class="grow bg-transparent outline-none text-sm"
                               autofocus
                               autocomplete="username"/>
                    </label>
                    @error('username')
                    <label class="label pt-1">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="form-control mb-6" x-data="{ showPass: false }">
                    <label class="label pb-1">
                        <span class="label-text font-medium">Password</span>
                    </label>
                    <label class="input input-bordered flex items-center gap-2 {{ $errors->has('password') ? 'input-error' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                        </svg>
                        <input :type="showPass ? 'text' : 'password'"
                               name="password"
                               placeholder="Enter your password"
                               class="grow bg-transparent outline-none text-sm"
                               autocomplete="current-password"/>
                        <button type="button" @click="showPass = !showPass"
                                class="text-base-content/40 hover:text-base-content transition-colors">
                            <svg x-show="!showPass" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPass" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </label>
                    @error('password')
                    <label class="label pt-1">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                    @enderror
                </div>

                {{-- Submit Button --}}
                <button type="submit"
                        class="btn btn-primary w-full btn-md"
                        :disabled="loading">
                    <span x-show="!loading">Login</span>
                    <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                    <span x-show="loading">Signing in...</span>
                </button>

            </form>

        </div>
    </div>

    {{-- Version Footer --}}
    <p class="text-center text-xs text-base-content/30 mt-4">
        EPMS IOI v{{ config('app.version') }} &mdash; {{ date('Y') }}
    </p>

    {{-- Dark Mode Toggle --}}
    <div class="flex justify-center mt-2">
        <button @click="toggleTheme()"
                class="btn btn-ghost btn-xs text-base-content/30 gap-1">
            <svg x-show="theme === 'light'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
            </svg>
            <svg x-show="theme === 'dark'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/>
            </svg>
            <span x-text="theme === 'light' ? 'Dark mode' : 'Light mode'"></span>
        </button>
    </div>

</div>

<script>
    function loginPage() {
        return {
            loading: false,
            theme: localStorage.getItem('theme') || 'light',  // default LIGHT
            toggleTheme() {
                this.theme = this.theme === 'light' ? 'dark' : 'light';
                localStorage.setItem('theme', this.theme);
                document.documentElement.setAttribute('data-theme', this.theme);
            }
        }
    }
</script>

</body>
</html>
