<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'Krettel'))</title>
        <meta name="description" content="@yield('meta_description', 'Krettel - Your Personal Video Streaming Platform')">
        <meta name="keywords" content="@yield('meta_keywords', 'streaming, video, movies, tv shows, krettel')">
        
        <!-- Open Graph / Social Media Meta Tags -->
        <meta property="og:title" content="@yield('title', config('app.name', 'Krettel'))">
        <meta property="og:description" content="@yield('meta_description', 'Krettel - Your Personal Video Streaming Platform')">
        <meta property="og:image" content="@yield('meta_image', asset('images/logo.png'))">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:type" content="website">

        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="@yield('title', config('app.name', 'Krettel'))">
        <meta name="twitter:description" content="@yield('meta_description', 'Krettel - Your Personal Video Streaming Platform')">
        <meta name="twitter:image" content="@yield('meta_image', asset('images/logo.png'))">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-background text-text selection:bg-primary selection:text-white pb-24 lg:pb-0">
        
        <x-sticky-header />

        <div class="min-h-screen">
            <!-- Page Heading -->
            @isset($header)
                <header class="bg-card/50 shadow-md border-b border-border pt-16">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="pt-16">
                {{ $slot }}
            </main>
        </div>
        
        <footer class="bg-card/50 py-12 mt-12 border-t border-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-muted">
                <p>&copy; {{ date('Y') }} Krettel. All rights reserved.</p>
            </div>
        </footer>

        <x-mobile-nav />
    </body>
</html>
