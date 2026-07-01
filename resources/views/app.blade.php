<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{--
            ANTI-FOUC THEME SCRIPT — must stay FIRST in <head>, before @vite and
            before any render-blocking stylesheet. It runs synchronously during
            HTML parse (no async/defer) and sets the `dark` class on <html>
            BEFORE the browser paints, so there is never a flash of the wrong
            theme. Reads localStorage first (instant, no network), falling back
            to the OS prefers-color-scheme. The Vue useColorMode composable reads
            this same class as its source of truth on mount.
        --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('color-mode');
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var dark = stored === 'dark' || ((stored === null || stored === 'system') && prefersDark);
                    if (dark) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) { /* private mode / disabled storage — default to light */ }
            })();
        </script>

        <title inertia>{{ config('app.name', 'DVARO') }}</title>

        {{-- Inter — preconnected for fast load, with a system fallback chain in
             tailwind.config.js so text renders immediately even before it lands. --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-white text-ink-900 dark:bg-ink-950 dark:text-ink-100">
        @inertia
    </body>
</html>
