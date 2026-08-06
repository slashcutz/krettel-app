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
                
                <!-- Continue Watching (Matching watch page UI) -->
                @if(isset($watchHistory) && $watchHistory->isNotEmpty())
                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">Continue Watching</h2>
                        <a href="{{ route('my-list') }}" class="text-xs font-semibold text-red-600 hover:underline">See All</a>
                    </div>

                    <div class="flex space-x-3 overflow-x-auto no-scrollbar py-2">
                        @foreach($watchHistory as $history)
                            @if($history->video)
                                @php
                                    $v = $history->video;
                                    $img = $v->poster ?: $v->thumbnail ?: $v->terabox_image;
                                    $imgUrl = $img ? \App\Support\TeraBoxImage::url($img, 'video', $v->id) : 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=300&auto=format&fit=crop';
                                    
                                    // Calculate progress
                                    $progressPct = 0;
                                    $timeLeft = '';
                                    if ($v->duration > 0 && $history->progress > 0) {
                                        $progressPct = min(100, max(20, ($history->progress / $v->duration) * 100));
                                        $remaining = $v->duration - $history->progress;
                                        if ($remaining > 0) {
                                            $minutes = ceil($remaining / 60);
                                            if ($minutes >= 60) {
                                                $hours = floor($minutes / 60);
                                                $mins = $minutes % 60;
                                                $timeLeft = "{$hours}h {$mins}m left";
                                            } else {
                                                $timeLeft = "{$minutes}m left";
                                            }
                                        }
                                    } else {
                                        $progressPct = 40; // Default sample progress for UI display
                                    }
                                @endphp
                                <a href="{{ route('video.show', $v->slug ?: $v->id) }}" 
                                   class="min-w-[210px] max-w-[210px] bg-zinc-900/80 border border-zinc-800/80 rounded-2xl p-2 flex items-center space-x-3 relative overflow-hidden group cursor-pointer transition hover:border-zinc-600 flex-shrink-0">
                                    <div class="relative w-16 h-16 rounded-xl overflow-hidden flex-shrink-0">
                                        <img src="{{ $imgUrl }}" alt="{{ $v->title }}" class="w-full h-full object-cover" loading="eager" fetchpriority="high" decoding="async">
                                        <div class="absolute inset-0 bg-black/20 flex items-center justify-center">
                                            <svg class="w-4 h-4 fill-white text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-xs font-bold text-white truncate">{{ $v->title }}</h3>
                                        <p class="text-[11px] text-zinc-400 mt-0.5">{{ $timeLeft ?: ($v->duration ? gmdate('H:i', $v->duration) : 'Movie') }}</p>
                                        <div class="w-full h-1 bg-zinc-800 rounded-full mt-2 overflow-hidden">
                                            <div class="bg-red-600 h-full" style="width: {{ $progressPct }}%"></div>
                                        </div>
                                    </div>
                                </a>
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
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl md:text-2xl font-bold text-white">Trending Now</h2>
                        <a href="{{ route('search.index') }}" class="text-xs font-semibold text-red-600 hover:underline">See All</a>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @forelse($trending as $video)
                            <div class="w-[calc(50%-8px)] sm:w-52 md:w-60 flex-shrink-0">
                                <x-video-card :video="$video" />
                            </div>
                        @empty
                            <p class="text-muted">No trending videos yet.</p>
                        @endforelse
                    </div>
                </section>

                <!-- New Releases -->
                <section>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl md:text-2xl font-bold text-white">New Releases</h2>
                        <a href="{{ route('search.index') }}" class="text-xs font-semibold text-red-600 hover:underline">See All</a>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @forelse($newReleases as $video)
                            <div class="w-[calc(50%-8px)] sm:w-52 md:w-60 flex-shrink-0">
                                <x-video-card :video="$video" />
                            </div>
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
