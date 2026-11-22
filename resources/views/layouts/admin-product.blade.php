<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Add Product') · Best Choice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @vite(['resources/css/admin.css', 'resources/css/admin-overrides.css', 'resources/js/admin.js'])
</head>
<body class="product-reference-page">
    <header class="product-reference-header">
        <div class="product-reference-topbar"><span class="product-reference-mark">BC</span><strong>Best Choice</strong><span>Store admin</span></div>
    </header>
    @yield('content')
</body>
</html>
