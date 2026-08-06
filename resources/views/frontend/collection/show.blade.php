<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $collection->name }} - {{ config('app.name', 'Krettel') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-background text-text selection:bg-primary selection:text-white pb-24 lg:pb-0">

        <x-sticky-header />

        @php
            $image = $collection->terabox_image ?: $collection->image;
            $heroUrl = $image ? \App\Support\TeraBoxImage::url($image, 'collection', $collection->id) : null;
        @endphp

        <!-- Immersive Hero -->
        <div class="relative w-full min-h-[75vh] flex items-end overflow-hidden">
            <div class="absolute inset-0">
                @if($heroUrl)
                    <img src="{{ $heroUrl }}" alt="{{ $collection->name }}" class="w-full h-full object-cover scale-105">
                @else
                    <div class="w-full h-full bg-gradient-to-br from-primary/40 via-purple-900/40 to-background"></div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-background via-background/50 to-background/20"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-background/80 via-transparent to-transparent"></div>
            </div>

            <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-14 md:pb-20">
                <button onclick="window.history.back()" class="mb-6 inline-flex items-center text-muted hover:text-white transition-colors group">
                    <svg class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back
                </button>

                <div class="flex flex-col md:flex-row md:items-end gap-6">
                    @if($heroUrl)
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-2xl overflow-hidden border-4 border-gray-800/80 shadow-2xl">
                            <img src="{{ $heroUrl }}" alt="{{ $collection->name }}" class="w-full h-full object-cover">
                        </div>
                    </div>
                    @endif

                    <div class="flex-1">
                        <span class="inline-flex items-center text-xs font-bold uppercase tracking-widest text-primary mb-3">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            Collection
                        </span>
                        <h1 class="text-4xl md:text-6xl font-extrabold text-white tracking-tight drop-shadow-xl">{{ $collection->name }}</h1>
                        <div class="flex items-center flex-wrap gap-3 text-sm text-gray-300 mt-4">
                            <span class="flex items-center text-gray-400">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                {{ $videos->count() }} {{ Str::plural('video', $videos->count()) }}
                            </span>
                            <span class="flex items-center text-gray-400">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                {{ $collection->created_at->format('M Y') }}
                            </span>
                            <span class="text-success font-semibold">{{ $videos->count() > 0 ? 'Available Now' : 'Coming Soon' }}</span>
                        </div>
                        @if($collection->description)
                            <p class="text-gray-300 leading-relaxed mt-4 max-w-3xl text-lg">{{ $collection->description }}</p>
                        @endif
                        <div class="flex flex-wrap items-center gap-3 mt-6">
                            <a href="#videos" class="inline-flex items-center bg-white text-black hover:bg-gray-200 font-bold px-6 py-3 rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                Browse Collection
                            </a>
                            <a href="{{ route('search.index') }}" class="inline-flex items-center border border-gray-600 text-white hover:border-white px-6 py-3 rounded-lg transition-colors font-medium">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                Discover More
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Videos Grid -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20 pb-32">
            <div id="videos" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <h2 class="text-xl md:text-2xl font-bold text-white">Videos in {{ $collection->name }}</h2>
                <select onchange="window.location.href = '{{ route('collection.show', $collection->slug) }}?sort=' + this.value" class="bg-card border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full sm:w-56 p-2.5">
                    <option value="latest" {{ request('sort') == 'latest' || !request('sort') ? 'selected' : '' }}>Sort by Latest</option>
                    <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Most Popular</option>
                    <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>Top Rated</option>
                </select>
            </div>

            @if($videos->count() > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    @foreach($videos as $video)
                        <x-video-card :video="$video" />
                    @endforeach
                </div>
            @else
                <div class="bg-card/50 border border-border rounded-2xl p-16 text-center">
                    <svg class="w-16 h-16 text-muted mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    <h3 class="text-2xl font-bold text-white mb-2">No videos in this collection</h3>
                    <p class="text-muted">There are currently no public videos in this collection. Check back later!</p>
                </div>
            @endif

            <!-- More Collections -->
            @if($related->isNotEmpty())
            <div class="mt-16">
                <h2 class="text-xl md:text-2xl font-bold text-white mb-4">More Collections</h2>
                <div class="flex overflow-x-auto pb-4 space-x-6 scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent">
                    @foreach($related as $rel)
                        <x-collection-circle :collection="$rel" />
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Footer -->
        <footer class="bg-card/50 py-12 mt-12 border-t border-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-muted">
                <p>&copy; {{ date('Y') }} Krettel. All rights reserved.</p>
            </div>
        </footer>

        <x-mobile-nav />
    </body>
</html>
