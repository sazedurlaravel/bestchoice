<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'আমার অ্যাকাউন্ট') | Best Choice</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
    <main class="auth-shell">
        @yield('content')
    </main>
</body>
</html>
