<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $storeName }}</title>
    @php
        $themePrimary = $themeSettings['theme_primary_color'] ?? '#164c39';
        $themeAccent = $themeSettings['theme_accent_color'] ?? '#d9903b';
        $themeBackground = $themeSettings['theme_background_color'] ?? '#faf8f1';
        $headerBackground = $themeSettings['header_background_color'] ?? '#ffffff';
        $footerBackground = $themeSettings['footer_background_color'] ?? '#153f30';
    @endphp
    <style>:root{--theme-primary:{{ $themePrimary }};--theme-accent:{{ $themeAccent }};--theme-background:{{ $themeBackground }};--theme-header-background:{{ $headerBackground }};--theme-footer-background:{{ $footerBackground }};--bc-green:{{ $themePrimary }};--bc-gold:{{ $themeAccent }};--bc-background:{{ $themeBackground }}}</style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
</head>
<body>
    @include('storefront.partials.header')
    @include('storefront.partials.mobile-bottom-nav')

    <main class="wrap">
        @unless(request()->routeIs('cart.index'))
            @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif
        @endunless
        @yield('content')
    </main>

    @include('storefront.partials.footer')
    @include('storefront.partials.cart-drawer')
    @include('storefront.partials.variant-picker')
    <div class="overlay" id="overlay"></div>
    <div class="toast" id="toast"></div>
</body>
</html>
