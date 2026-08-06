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
    <body class="font-sans antialiased bg-[#08080A] text-text selection:bg-primary selection:text-white pb-24 lg:pb-0">
        
        <!-- Sticky Header -->
        <x-sticky-header />

        <main class="min-h-screen">
            <!-- Hero Banner -->
            <x-hero-banner :video="$featured" />

            <!-- Video Sections -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-20 space-y-10">
                
                <!-- Continue Watching -->
                @if(isset($watchHistory) && $watchHistory->isNotEmpty())
                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold tracking-wide text-white">Continue Watching</h2>
                        <a href="{{ route('my-list') }}" class="text-xs font-semibold text-red-600 hover:underline">See All</a>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @foreach($watchHistory as $history)
                            @if($history->video)
                                @php
                                    $v = $history->video;
                                    $img = $v->thumbnail ?: $v->poster ?: $v->terabox_image;
                                    $imgUrl = $img ? \App\Support\TeraBoxImage::url($img, 'video', $v->id) : 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?q=80&w=400&auto=format&fit=crop';
                                    
                                    // Calculate progress
                                    $progressPct = 0;
                                    $timeLeft = '';
                                    if ($v->duration > 0 && $history->progress > 0) {
                                        $progressPct = ($history->progress / $v->duration) * 100;
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
                                    }
                                @endphp
                                <a href="{{ route('video.show', $v->slug ?: $v->id) }}" 
                                   class="min-w-[200px] sm:min-w-[240px] aspect-[16/10] rounded-2xl overflow-hidden relative group cursor-pointer border border-zinc-800/80 bg-zinc-900 flex-shrink-0 block">
                                    <img src="{{ $imgUrl }}" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent"></div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-10 h-10 rounded-full bg-black/50 backdrop-blur-md border border-white/20 flex items-center justify-center group-hover:scale-110 transition">
                                            <svg class="w-4 h-4 fill-white text-white ml-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                        </div>
                                    </div>
                                    <div class="absolute bottom-3 left-3 right-3 space-y-1">
                                        <h3 class="text-xs font-bold text-white truncate">{{ $v->title }}</h3>
                                        @if($timeLeft)
                                        <p class="text-[10px] text-zinc-400">{{ $timeLeft }}</p>
                                        <div class="w-full h-1 bg-zinc-800 rounded-full overflow-hidden">
                                            <div class="bg-netflix-red h-full" style="width: {{ $progressPct }}%"></div>
                                        </div>
                                        @endif
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
                @endif

                <!-- My List -->
                @if(isset($favorites) && $favorites->isNotEmpty())
                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold tracking-wide text-white">My List</h2>
                        <a href="{{ route('my-list') }}" class="text-xs font-semibold text-red-600 hover:underline">See All</a>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @foreach($favorites as $favorite)
                            @if($favorite->video)
                                @php
                                    $v = $favorite->video;
                                    $img = $v->thumbnail ?: $v->poster ?: $v->terabox_image;
                                    $imgUrl = $img ? \App\Support\TeraBoxImage::url($img, 'video', $v->id) : 'https://images.unsplash.com/photo-1578632767115-351597cf2477?q=80&w=400&auto=format&fit=crop';
                                @endphp
                                <a href="{{ route('video.show', $v->slug ?: $v->id) }}" 
                                   class="min-w-[170px] sm:min-w-[200px] aspect-[16/10] rounded-2xl overflow-hidden relative group cursor-pointer border border-zinc-800/80 bg-zinc-900 flex-shrink-0 block">
                                    <img src="{{ $imgUrl }}" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
                                    <button class="absolute top-2.5 right-2.5 p-1.5 rounded-full bg-black/40 backdrop-blur-md border border-white/20 text-white">
                                        <svg class="w-3.5 h-3.5 fill-white" viewBox="0 0 24 24" fill="currentColor"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                                    </button>
                                    <div class="absolute bottom-3 left-3">
                                        <h3 class="text-xs font-bold text-white truncate max-w-[150px]">{{ $v->title }}</h3>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
                @endif

                <!-- Collections -->
                @if($collections->isNotEmpty())
                <section id="collections" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold tracking-wide text-white">Collections</h2>
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
                               class="min-w-[220px] bg-zinc-900/90 border border-zinc-800/80 rounded-2xl p-2.5 flex items-center space-x-3 flex-shrink-0 cursor-pointer hover:bg-zinc-800/60 transition block">
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
                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold tracking-wide text-white">Trending Now</h2>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @forelse($trending as $video)
                            @php
                                $img = $video->thumbnail ?: $video->poster ?: $video->terabox_image;
                                $imgUrl = $img ? \App\Support\TeraBoxImage::url($img, 'video', $video->id) : 'https://images.unsplash.com/photo-1534447677768-be436bb09401?q=80&w=400&auto=format&fit=crop';
                            @endphp
                            <a href="{{ route('video.show', $video->slug ?: $video->id) }}" 
                               class="min-w-[170px] sm:min-w-[200px] aspect-[16/10] rounded-2xl overflow-hidden relative group cursor-pointer border border-zinc-800/80 bg-zinc-900 flex-shrink-0 block">
                                <img src="{{ $imgUrl }}" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
                                <!-- Flame Badge -->
                                <div class="absolute top-2.5 left-2.5 w-6 h-6 rounded-full bg-netflix-red flex items-center justify-center shadow-lg">
                                    <svg class="w-3.5 h-3.5 fill-white text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                                </div>
                            </a>
                        @empty
                            <p class="text-muted">No trending videos yet.</p>
                        @endforelse
                    </div>
                </section>

                <!-- New Releases -->
                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold tracking-wide text-white">New Releases</h2>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @forelse($newReleases as $video)
                            @php
                                $img = $video->thumbnail ?: $video->poster ?: $video->terabox_image;
                                $imgUrl = $img ? \App\Support\TeraBoxImage::url($img, 'video', $video->id) : 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=400&auto=format&fit=crop';
                            @endphp
                            <a href="{{ route('video.show', $video->slug ?: $video->id) }}" 
                               class="min-w-[200px] sm:min-w-[240px] aspect-[16/10] rounded-2xl overflow-hidden relative group cursor-pointer border border-zinc-800/80 bg-zinc-900 flex-shrink-0 block">
                                <img src="{{ $imgUrl }}" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent"></div>
                                <div class="absolute bottom-3 left-3">
                                    <h3 class="text-xs font-bold text-white truncate max-w-[180px]">{{ $video->title }}</h3>
                                </div>
                            </a>
                        @empty
                            <p class="text-muted">No new releases yet.</p>
                        @endforelse
                    </div>
                </section>

                <!-- Recommended -->
                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold tracking-wide text-white">Recommended for You</h2>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto no-scrollbar py-1">
                        @forelse($recommended as $video)
                            @php
                                $img = $video->thumbnail ?: $video->poster ?: $video->terabox_image;
                                $imgUrl = $img ? \App\Support\TeraBoxImage::url($img, 'video', $video->id) : 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=400&auto=format&fit=crop';
                            @endphp
                            <a href="{{ route('video.show', $video->slug ?: $video->id) }}" 
                               class="min-w-[200px] sm:min-w-[240px] aspect-[16/10] rounded-2xl overflow-hidden relative group cursor-pointer border border-zinc-800/80 bg-zinc-900 flex-shrink-0 block">
                                <img src="{{ $imgUrl }}" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent"></div>
                                <div class="absolute bottom-3 left-3">
                                    <h3 class="text-xs font-bold text-white truncate max-w-[180px]">{{ $video->title }}</h3>
                                </div>
                            </a>
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
