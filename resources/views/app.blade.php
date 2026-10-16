<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'LuxMonitor') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- Áp theme trước khi vẽ để không nháy sáng/tối. Mặc định là dark, giống 9router. --}}
    <script>
        (function () {
            document.documentElement.classList.toggle(
                'dark',
                localStorage.getItem('lux_theme') !== 'light'
            );
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-page font-sans text-ink antialiased">
    <div id="app" class="h-full"></div>
</body>
</html>
