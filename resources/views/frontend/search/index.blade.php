<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $query ? 'Search: ' . $query : 'Browse' }} - {{ config('app.name', 'Krettel') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    </head>
    <body class="font-sans antialiased bg-background text-text selection:bg-primary selection:text-white pb-24 lg:pb-0">

        @php
            $f = $filters;
            $urlFor = function (array $overrides) use ($f, $query) {
                $params = array_filter(array_merge(
                    array_filter(['q' => $query !== '' ? $query : null]),
                    $f,
                    $overrides
                ), fn ($v) => $v !== null && $v !== '');

                return route('search.index', $params);
            };
            $chipCls = function (bool $active) {
                return $active
                    ? 'flex-shrink-0 px-4 py-2 rounded-full bg-primary text-white text-xs font-bold transition-colors'
                    : 'flex-shrink-0 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-gray-300 text-xs font-medium hover:bg-white/10 hover:text-white transition-colors';
            };
        @endphp

        <x-sticky-header />

        <main class="min-h-screen pt-20 md:pt-28">

            <!-- Search + Filter Bar (sticky) -->
            <div class="sticky top-20 md:top-28 z-40 bg-background/90 backdrop-blur-xl border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 py-4 space-y-3">
                    <div class="max-w-lg mx-auto">
                        <form action="{{ route('search.index') }}" method="GET" class="relative">
                            <svg class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <input type="text" name="q" value="{{ $query }}" placeholder="Search videos, genres..." autocomplete="off"
                                   class="w-full bg-white/5 border border-white/10 text-white text-sm rounded-2xl pl-11 pr-10 py-3.5 focus:ring-primary focus:border-primary placeholder-gray-500">
                            @if($query !== '')
                            <a href="{{ route('search.index') }}" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors" aria-label="Clear search">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </a>
                            @else
                            <button type="submit" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-primary" aria-label="Search">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                            </button>
                            @endif
                        </form>
                    </div>

                    <!-- Categories -->
                    <div class="flex gap-2 overflow-x-auto no-scrollbar">
                        <a href="{{ $urlFor(['category' => null]) }}" class="{{ $chipCls(!$f['category']) }}">All</a>
                        @foreach($categories as $category)
                            <a href="{{ $urlFor(['category' => $category->id]) }}" class="{{ $chipCls($f['category'] == $category->id) }}">{{ $category->name }}</a>
                        @endforeach
                    </div>

                    <!-- Genres -->
                    @if($genres->isNotEmpty())
                    <div class="flex gap-2 overflow-x-auto no-scrollbar">
                        <a href="{{ $urlFor(['genre' => null]) }}" class="{{ $chipCls(!$f['genre']) }}">All Genres</a>
                        @foreach($genres as $genre)
                            <a href="{{ $urlFor(['genre' => $genre->id]) }}" class="{{ $chipCls($f['genre'] == $genre->id) }}">{{ $genre->name }}</a>
                        @endforeach
                    </div>
                    @endif

                    <!-- More filters -->
                    <div class="flex gap-2 overflow-x-auto no-scrollbar">
                        <select onchange="window.location.href = '{{ $urlFor(['language' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', this.value || '')" class="flex-shrink-0 bg-white/5 border border-white/10 text-gray-300 text-xs font-medium rounded-full px-4 py-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer">
                            <option value="" {{ !$f['language'] ? 'selected' : '' }}>Language</option>
                            @foreach($languages as $language)
                                <option value="{{ $language->id }}" {{ $f['language'] == $language->id ? 'selected' : '' }}>{{ $language->name }}</option>
                            @endforeach
                        </select>
                        <select onchange="window.location.href = '{{ $urlFor(['type' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', this.value || '')" class="flex-shrink-0 bg-white/5 border border-white/10 text-gray-300 text-xs font-medium rounded-full px-4 py-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer">
                            <option value="" {{ !$f['type'] ? 'selected' : '' }}>Type</option>
                            <option value="movie" {{ $f['type'] == 'movie' ? 'selected' : '' }}>Movies</option>
                            <option value="tv_show" {{ $f['type'] == 'tv_show' ? 'selected' : '' }}>TV Shows</option>
                        </select>
                        @if($resolutions->isNotEmpty())
                        <select onchange="window.location.href = '{{ $urlFor(['resolution' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', this.value || '')" class="flex-shrink-0 bg-white/5 border border-white/10 text-gray-300 text-xs font-medium rounded-full px-4 py-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer">
                            <option value="" {{ !$f['resolution'] ? 'selected' : '' }}>Quality</option>
                            @foreach($resolutions as $resolution)
                                <option value="{{ $resolution }}" {{ $f['resolution'] == $resolution ? 'selected' : '' }}>{{ $resolution }}</option>
                            @endforeach
                        </select>
                        @endif
                        @if($ratings->isNotEmpty())
                        <select onchange="window.location.href = '{{ $urlFor(['rating' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', this.value || '')" class="flex-shrink-0 bg-white/5 border border-white/10 text-gray-300 text-xs font-medium rounded-full px-4 py-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer">
                            <option value="" {{ !$f['rating'] ? 'selected' : '' }}>Rating</option>
                            @foreach($ratings as $rating)
                                <option value="{{ $rating }}" {{ $f['rating'] == $rating ? 'selected' : '' }}>{{ $rating }}</option>
                            @endforeach
                        </select>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Results -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-white">
                            @if($query)
                                Results for "<span class="text-primary">{{ $query }}</span>"
                            @else
                                Browse Videos
                            @endif
                        </h1>
                        <p class="text-xs text-gray-500 mt-1">{{ $videos->total() }} {{ Str::plural('video', $videos->total()) }} found</p>
                    </div>

                    <div class="inline-flex bg-white/5 border border-white/10 rounded-full p-1">
                        <a href="{{ $urlFor(['sort' => 'latest']) }}" class="{{ $f['sort'] == 'latest' ? 'bg-white text-black' : 'text-gray-300' }} text-xs font-bold px-4 py-1.5 rounded-full transition-colors">Latest</a>
                        <a href="{{ $urlFor(['sort' => 'popular']) }}" class="{{ $f['sort'] == 'popular' ? 'bg-white text-black' : 'text-gray-300' }} text-xs font-bold px-4 py-1.5 rounded-full transition-colors">Popular</a>
                        <a href="{{ $urlFor(['sort' => 'rating']) }}" class="{{ $f['sort'] == 'rating' ? 'bg-white text-black' : 'text-gray-300' }} text-xs font-bold px-4 py-1.5 rounded-full transition-colors">Top Rated</a>
                    </div>
                </div>

                @if($videos->count() > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        @foreach($videos as $video)
                            <x-video-card :video="$video" />
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $videos->links() }}
                    </div>
                @else
                    <div class="bg-card/60 border border-white/5 rounded-3xl p-14 text-center">
                        <div class="w-16 h-16 mx-auto rounded-full bg-white/5 flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">No videos found</h3>
                        <p class="text-muted text-sm">Try a different search or clear some filters.</p>
                        <a href="{{ route('search.index') }}" class="inline-flex items-center mt-6 bg-primary hover:bg-red-600 text-white font-bold text-sm px-6 py-2.5 rounded-xl transition-colors">
                            Clear All Filters
                        </a>
                    </div>
                @endif
            </div>

        </main>

        <x-mobile-nav />
    </body>
</html>
