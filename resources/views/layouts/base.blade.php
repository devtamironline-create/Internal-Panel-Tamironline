<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'سیستم مدیریت هاستینگ')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="/css/fonts.css" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>* { font-family: 'Rokh', sans-serif; }</style>
    @stack('styles')
</head>
<body class="bg-gray-100 antialiased">
    @yield('content')
    @stack('scripts')
</body>
</html>
