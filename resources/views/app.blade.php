<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- No enviamos Referer para que las imágenes externas de dazimportadora
         no sean bloqueadas por su hotlink protection. --}}
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="api-url" content="{{ url('/api') }}">
    <title>@yield('title', config('app.name', 'Ecomers'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block" rel="stylesheet">
    {{-- PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0f172a">
    {{-- mobile-web-app-capable es el estándar moderno; apple-* es fallback para Safari iOS --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Tecno-Rexs">
    @vite(['resources/css/app.css', 'resources/ts/app.ts'])
</head>
<body class="antialiased">
    <div id="app"></div>
    <script>
        // Registrar Service Worker para PWA (offline básico + cache de assets)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {
                    // Si falla (HTTP, file://, etc.) no es crítico.
                });
            });
        }
    </script>
</body>
</html>
