<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Collections - {{ config('app.name', 'Krettel') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-background text-text selection:bg-primary selection:text-white pb-24 lg:pb-0">

        <x-sticky-header />

        <main class="min-h-screen pt-20 md:pt-28">
            <!-- Page Header -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                    <div>
                        <span class="inline-flex items-center text-xs font-bold uppercase tracking-widest text-primary mb-3">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            Curated Libraries
                        </span>
                        <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Collections</h1>
                        <p class="text-gray-400 mt-2">Hand-picked groups of titles, ready to stream.</p>
                    </div>
                    <span class="text-sm text-gray-400 font-medium">{{ $collections->total() }} {{ Str::plural('collection', $collections->total()) }}</span>
                </div>

                <!-- Poster Cards Grid -->
                @if($collections->count() > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-4 sm:gap-5">
                        @foreach($collections as $collection)
                            <x-collection-poster :collection="$collection" />
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $collections->links() }}
                    </div>
                @else
                    <div class="bg-card/50 border border-border rounded-2xl p-16 text-center">
                        <svg class="w-16 h-16 text-muted mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        <h3 class="text-2xl font-bold text-white mb-2">No collections yet</h3>
                        <p class="text-muted">Check back soon for new curated collections!</p>
                    </div>
                @endif
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-card/50 py-12 mt-12 border-t border-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-muted">
                <div class="flex flex-wrap items-center justify-center gap-6 mb-4 text-sm font-medium">
                    <a href="{{ route('home') }}" class="hover:text-white transition-colors">Home</a>
                    <a href="{{ route('collections.index') }}" class="text-primary">Collections</a>
                    <a href="{{ route('search.index') }}" class="hover:text-white transition-colors">Categories</a>
                    <a href="{{ route('my-list') }}" class="hover:text-white transition-colors">My List</a>
                </div>
                <p>&copy; {{ date('Y') }} Krettel. All rights reserved.</p>
            </div>
        </footer>

        <x-mobile-nav />
    </body>
</html>
