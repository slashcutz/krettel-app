<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Krettel') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-background text-text selection:bg-primary selection:text-white pb-24 lg:pb-0">
        
        <!-- Sticky Header -->
        <x-sticky-header />

        <main class="min-h-screen">
            <!-- Hero Banner -->
            <x-hero-banner :video="$featured" />

            <!-- Video Sections -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 -mt-20 md:-mt-32 relative z-20 space-y-12">
                
                <!-- Collections -->
                @if($collections->isNotEmpty())
                <section id="collections">
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-4">Collections</h2>
                    <div class="flex overflow-x-auto pb-4 space-x-6 scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent">
                        @foreach($collections as $collection)
                            <x-collection-circle :collection="$collection" />
                        @endforeach
                    </div>
                </section>
                @endif

                <!-- Trending Now -->
                <section>
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-4">Trending Now</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        @forelse($trending as $video)
                            <x-video-card :video="$video" />
                        @empty
                            <p class="text-muted">No trending videos yet.</p>
                        @endforelse
                    </div>
                </section>

                <!-- New Releases -->
                <section>
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-4">New Releases</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        @forelse($newReleases as $video)
                            <x-video-card :video="$video" />
                        @empty
                            <p class="text-muted">No new releases yet.</p>
                        @endforelse
                    </div>
                </section>

                <!-- Recommended -->
                <section>
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-4">Recommended for You</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        @forelse($recommended as $video)
                            <x-video-card :video="$video" />
                        @empty
                            <p class="text-muted">No recommendations yet.</p>
                        @endforelse
                    </div>
                </section>

            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-card/50 py-12 mt-12 border-t border-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-muted">
                <p>&copy; {{ date('Y') }} Krettel. All rights reserved.</p>
            </div>
        </footer>

        <x-mobile-nav />
    </body>
</html>
