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

    {{-- ============== TÍTULO ============== --}}
    <title>{{ $seoTitle ?? config('app.name', 'Ecomers') }}@if(!empty($seoTitle) && $seoTitle !== config('app.name')) · {{ config('app.name', 'Tecno-Rexs') }}@endif</title>

    {{-- ============== META TAGS BASE ============== --}}
    <meta name="description" content="{{ $seoDescription ?? 'Tecno-Rexs — Catálogo de productos de tecnología con los mejores precios. Smartphones, notebooks, audio, gaming y más.' }}">
    <link rel="canonical" href="{{ $seoUrl ?? url()->current() }}">

    {{-- ============== OPEN GRAPH (Facebook, WhatsApp, LinkedIn, iMessage) ============== --}}
    @php
        $ogType = $seoType ?? 'website';
        $ogTitle = $seoTitle ?? (config('app.name', 'Tecno-Rexs') . ' — Catálogo de tecnología');
        $ogDescription = $seoDescription ?? 'Catálogo de productos de tecnología con los mejores precios. Smartphones, notebooks, audio, gaming y más.';
        $ogImage = $seoImage ?? asset('icons/icon-512.png');
        $ogUrl = $seoUrl ?? url()->current();
    @endphp
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ config('app.name', 'Tecno-Rexs') }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="es_AR">
    @if($ogType === 'product' && isset($seoProduct))
        <meta property="product:price:amount" content="{{ number_format((float)($seoProduct->final_price ?? $seoProduct->price), 2, '.', '') }}">
        <meta property="product:price:currency" content="ARS">
    @endif

    {{-- ============== TWITTER CARD (X / Twitter) ============== --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    {{-- ============== FONTS ============== --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block" rel="stylesheet">

    {{-- ============== PWA ============== --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Tecno-Rexs">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

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
