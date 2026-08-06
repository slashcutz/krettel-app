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

        <style>
            /* Hide scrollbar for smooth horizontal scrolling */
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

            /* Netflix Accent Red Colors */
            .netflix-red { color: #E50914; }
            .bg-netflix-red { background-color: #E50914; }
        </style>
    </head>
    <body class="font-sans antialiased bg-background text-text selection:bg-primary selection:text-white pb-24 lg:pb-0">
        
        <!-- Sticky Header -->
        <x-sticky-header />

        <main class="min-h-screen">
            <!-- Hero Banner -->
            <x-hero-banner :video="$featured" />

            <!-- Video Sections -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-20 space-y-12">
                
                <!-- Continue Watching -->
                @if(isset($watchHistory) && $watchHistory->isNotEmpty())
                <section>
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-4">Continue Watching</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        @foreach($watchHistory as $history)
                            @if($history->video)
                                <x-video-card :video="$history->video" />
                            @endif
                        @endforeach
                    </div>
                </section>
                @endif

                <!-- My List -->
                @if(isset($favorites) && $favorites->isNotEmpty())
                <section>
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-4">My List</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        @foreach($favorites as $favorite)
                            @if($favorite->video)
                                <x-video-card :video="$favorite->video" />
                            @endif
                        @endforeach
                    </div>
                </section>
                @endif

                <!-- Collections -->
                @if($collections->isNotEmpty())
                <section id="collections" class="space-y-3">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xl md:text-2xl font-bold text-white">Collections</h2>
                        <a href="{{ route('collections.index') }}" class="text-xs font-semibold text-red-600 hover:underline">See All</a>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @foreach($collections as $collection)
                            @php
                                $cImage = data_get($collection, 'terabox_image') ?: data_get($collection, 'image');
                                $cImageUrl = $cImage ? \App\Support\TeraBoxImage::url($cImage, 'collection', data_get($collection, 'id')) : 'https://via.placeholder.com/200x200?text=' . urlencode(data_get($collection, 'name'));
                                $videosCount = $collection->videos ? $collection->videos->count() : 0;
                            @endphp
                            <a href="{{ route('collection.show', data_get($collection, 'slug', data_get($collection, 'id', 'sample-collection'))) }}" 
                               class="w-[calc(50%-8px)] sm:w-auto sm:min-w-[220px] bg-zinc-900/90 border border-zinc-800/80 rounded-2xl p-2.5 flex items-center space-x-3 flex-shrink-0 cursor-pointer hover:bg-zinc-800/60 transition block">
                                <div class="w-12 h-12 rounded-full overflow-hidden relative flex-shrink-0 border border-zinc-700">
                                    <img src="{{ $cImageUrl }}" alt="{{ data_get($collection, 'name') }}" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-xs font-bold text-white truncate">{{ data_get($collection, 'name') }}</h3>
                                    <p class="text-[10px] text-zinc-400">{{ $videosCount }}+ Titles</p>
                                </div>
                            </a>
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
