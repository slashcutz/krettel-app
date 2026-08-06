<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-text antialiased selection:bg-primary selection:text-white" 
          style="background-image: linear-gradient(rgba(11, 11, 11, 0.7), rgba(11, 11, 11, 1)), url('https://images.unsplash.com/photo-1574267432553-4b4628081524?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; background-attachment: fixed;">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div>
                <a href="/">
                    <!-- Placeholder Logo -->
                    <h1 class="text-4xl font-bold tracking-tighter text-primary">KRETTEL</h1>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-8 px-8 py-10 bg-card/80 backdrop-blur-xl shadow-soft overflow-hidden sm:rounded-3xl border border-border transition-all duration-300 hover:shadow-[0_10px_40px_-10px_rgba(239,68,68,0.15)]">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
