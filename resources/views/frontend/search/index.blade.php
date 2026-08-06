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
            .bottom-sheet-content { animation: sheetUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
            @keyframes sheetUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
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
            $activeCount = collect($f)->filter(fn($v, $k) => $v && $k !== 'sort')->count();
        @endphp

        <x-sticky-header />

        <main class="min-h-screen pt-16 md:pt-20"
              x-data="{
                  searchQuery: '{{ addslashes($query) }}',
                  results: [],
                  loading: false,
                  debounceTimer: null,
                  showLive: false,
                  showFilters: false,
                  activeDesktopFilter: null,

                  liveSearch() {
                      clearTimeout(this.debounceTimer);
                      const q = this.searchQuery.trim();
                      if (q.length < 2) { this.results = []; this.showLive = false; return; }
                      this.loading = true;
                      this.showLive = true;
                      this.debounceTimer = setTimeout(async () => {
                          try {
                              const resp = await fetch(`{{ route('search.suggest') }}?q=${encodeURIComponent(q)}`);
                              const data = await resp.json();
                              this.results = data.videos || [];
                          } catch(e) { this.results = []; }
                          this.loading = false;
                      }, 300);
                  },
                  closeLive() { setTimeout(() => { this.showLive = false; }, 200); },
                  toggleDesktopFilter(name) { this.activeDesktopFilter = this.activeDesktopFilter === name ? null : name; }
              }"
              @keydown.escape.window="showFilters = false; activeDesktopFilter = null">

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- MOBILE: Only search bar + filter button is sticky  --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            <div class="sticky top-16 md:top-20 z-40 bg-background/95 backdrop-blur-xl border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5">

                    <!-- Search + Filter trigger row -->
                    <div class="flex items-center gap-2">
                        <!-- Search Input -->
                        <div class="relative flex-1">
                            <form action="{{ route('search.index') }}" method="GET" class="relative">
                                <svg class="w-4 h-4 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" name="q" 
                                       x-model="searchQuery"
                                       @input="liveSearch()"
                                       @focus="if(results.length) showLive = true"
                                       @blur="closeLive()"
                                       placeholder="Search videos..." 
                                       autocomplete="off"
                                       class="w-full bg-white/5 border border-white/8 text-white text-sm rounded-xl pl-9 pr-8 py-2 focus:ring-1 focus:ring-primary focus:border-primary placeholder-gray-500 transition-all">
                                <template x-if="searchQuery !== ''">
                                    <button type="button" @click="searchQuery = ''; results = []; showLive = false;" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </template>
                            </form>

                            <!-- Live dropdown -->
                            <div x-show="showLive && (results.length > 0 || loading)" x-transition
                                 class="absolute top-full left-0 right-0 mt-1 bg-zinc-900/98 backdrop-blur-xl border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                <template x-if="loading && results.length === 0">
                                    <div class="p-3 text-center"><div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto"></div></div>
                                </template>
                                <template x-if="results.length > 0">
                                    <div class="max-h-56 overflow-y-auto divide-y divide-white/5">
                                        <template x-for="video in results" :key="video.id">
                                            <a :href="`/watch/${video.slug}`" class="flex items-center gap-2.5 px-3 py-2 hover:bg-white/5 transition-colors">
                                                <div class="w-11 h-7 rounded-md overflow-hidden flex-shrink-0 bg-zinc-800">
                                                    <img :src="video.thumbnail" :alt="video.title" class="w-full h-full object-cover" loading="lazy">
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="text-xs font-semibold text-white truncate" x-text="video.title"></h4>
                                                    <span class="text-[10px] text-gray-500" x-text="(video.views || 0) + ' views'"></span>
                                                </div>
                                            </a>
                                        </template>
                                        <div class="px-3 py-2 text-center">
                                            <button type="button" @click="$el.closest('main').querySelector('form').submit()" class="text-[11px] text-primary font-bold hover:underline">View all →</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Mobile: Filter button -->
                        <button @click="showFilters = true" 
                                class="md:hidden relative flex-shrink-0 w-9 h-9 rounded-xl bg-white/5 border border-white/8 flex items-center justify-center text-gray-400 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                            @if($activeCount > 0)
                            <span class="absolute -top-1 -right-1 w-4 h-4 bg-primary text-white text-[9px] font-bold rounded-full flex items-center justify-center">{{ $activeCount }}</span>
                            @endif
                        </button>

                        <!-- Desktop: Sort toggle -->
                        <div class="hidden md:inline-flex flex-shrink-0 bg-white/5 rounded-lg p-0.5 border border-white/5">
                            <a href="{{ $urlFor(['sort' => 'latest']) }}" class="{{ $f['sort'] == 'latest' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[11px] font-bold px-3 py-1.5 rounded-md transition-colors">Latest</a>
                            <a href="{{ $urlFor(['sort' => 'popular']) }}" class="{{ $f['sort'] == 'popular' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[11px] font-bold px-3 py-1.5 rounded-md transition-colors">Popular</a>
                            <a href="{{ $urlFor(['sort' => 'rating']) }}" class="{{ $f['sort'] == 'rating' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[11px] font-bold px-3 py-1.5 rounded-md transition-colors">Top Rated</a>
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════════ --}}
                    {{-- DESKTOP ONLY: inline filter chips below search --}}
                    {{-- ═══════════════════════════════════════════════ --}}
                    <div class="hidden md:block mt-2.5 space-y-2">
                        <!-- Categories -->
                        <div class="flex gap-1.5 overflow-x-auto no-scrollbar">
                            <a href="{{ $urlFor(['category' => null]) }}" class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ !$f['category'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }}">All</a>
                            @foreach($categories as $category)
                                <a href="{{ $urlFor(['category' => $category->id]) }}" class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['category'] == $category->id ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }}">{{ $category->name }}</a>
                            @endforeach
                        </div>

                        <!-- Genres + Dropdowns row -->
                        <div class="flex items-center gap-2">
                            @if($genres->isNotEmpty())
                            <div class="flex gap-1.5 overflow-x-auto no-scrollbar flex-1">
                                <a href="{{ $urlFor(['genre' => null]) }}" class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ !$f['genre'] ? 'bg-red-600/20 text-red-400 border border-red-600/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">All Genres</a>
                                @foreach($genres as $genre)
                                    <a href="{{ $urlFor(['genre' => $genre->id]) }}" class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['genre'] == $genre->id ? 'bg-red-600/20 text-red-400 border border-red-600/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">{{ $genre->name }}</a>
                                @endforeach
                            </div>
                            @endif

                            <!-- Desktop filter dropdowns -->
                            @foreach([
                                ['key' => 'language', 'label' => 'Language', 'active' => $f['language'], 'activeLabel' => $f['language'] ? ($languages->firstWhere('id', $f['language'])?->name ?? 'Language') : 'Language'],
                                ['key' => 'type', 'label' => 'Type', 'active' => $f['type'], 'activeLabel' => $f['type'] == 'movie' ? 'Movies' : ($f['type'] == 'tv_show' ? 'TV Shows' : 'Type')],
                            ] as $filter)
                            <div class="relative flex-shrink-0" @click.outside="if(activeDesktopFilter==='{{ $filter['key'] }}') activeDesktopFilter = null">
                                <button @click="toggleDesktopFilter('{{ $filter['key'] }}')" 
                                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $filter['active'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">
                                    {{ $filter['activeLabel'] }}
                                    <svg class="w-2.5 h-2.5 transition-transform" :class="activeDesktopFilter === '{{ $filter['key'] }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div x-show="activeDesktopFilter === '{{ $filter['key'] }}'" x-transition
                                     class="absolute top-full right-0 mt-1.5 w-40 bg-zinc-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                    <div class="max-h-52 overflow-y-auto py-1">
                                        @if($filter['key'] === 'language')
                                            <a href="{{ $urlFor(['language' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['language'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">All</a>
                                            @foreach($languages as $lang)
                                                <a href="{{ $urlFor(['language' => $lang->id]) }}" class="block px-3 py-2 text-xs {{ $f['language'] == $lang->id ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">{{ $lang->name }}</a>
                                            @endforeach
                                        @elseif($filter['key'] === 'type')
                                            <a href="{{ $urlFor(['type' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['type'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">All</a>
                                            <a href="{{ $urlFor(['type' => 'movie']) }}" class="block px-3 py-2 text-xs {{ $f['type'] == 'movie' ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">Movies</a>
                                            <a href="{{ $urlFor(['type' => 'tv_show']) }}" class="block px-3 py-2 text-xs {{ $f['type'] == 'tv_show' ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">TV Shows</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach

                            @if($resolutions->isNotEmpty())
                            <div class="relative flex-shrink-0" @click.outside="if(activeDesktopFilter==='quality') activeDesktopFilter = null">
                                <button @click="toggleDesktopFilter('quality')" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['resolution'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">
                                    {{ $f['resolution'] ?: 'Quality' }}
                                    <svg class="w-2.5 h-2.5 transition-transform" :class="activeDesktopFilter === 'quality' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div x-show="activeDesktopFilter === 'quality'" x-transition class="absolute top-full right-0 mt-1.5 w-36 bg-zinc-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                    <div class="py-1">
                                        <a href="{{ $urlFor(['resolution' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['resolution'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">All</a>
                                        @foreach($resolutions as $res)
                                            <a href="{{ $urlFor(['resolution' => $res]) }}" class="block px-3 py-2 text-xs {{ $f['resolution'] == $res ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">{{ $res }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if($ratings->isNotEmpty())
                            <div class="relative flex-shrink-0" @click.outside="if(activeDesktopFilter==='rating') activeDesktopFilter = null">
                                <button @click="toggleDesktopFilter('rating')" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['rating'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">
                                    {{ $f['rating'] ?: 'Rating' }}
                                    <svg class="w-2.5 h-2.5 transition-transform" :class="activeDesktopFilter === 'rating' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div x-show="activeDesktopFilter === 'rating'" x-transition class="absolute top-full right-0 mt-1.5 w-36 bg-zinc-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                    <div class="py-1">
                                        <a href="{{ $urlFor(['rating' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['rating'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">All</a>
                                        @foreach($ratings as $rat)
                                            <a href="{{ $urlFor(['rating' => $rat]) }}" class="block px-3 py-2 text-xs {{ $f['rating'] == $rat ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }}">{{ $rat }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- MOBILE: Full-screen bottom sheet with ALL filters      --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div x-show="showFilters" x-cloak class="md:hidden fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showFilters = false" x-show="showFilters" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
                <div class="bottom-sheet-content absolute bottom-0 left-0 right-0 bg-zinc-900 border-t border-white/10 rounded-t-2xl" style="max-height: 80vh;">
                    <!-- Handle + Header -->
                    <div class="sticky top-0 bg-zinc-900 rounded-t-2xl z-10">
                        <div class="flex justify-center pt-3 pb-1"><div class="w-10 h-1 bg-white/20 rounded-full"></div></div>
                        <div class="flex items-center justify-between px-5 pb-3">
                            <h3 class="text-base font-bold text-white">Filters</h3>
                            <div class="flex items-center gap-3">
                                @if($activeCount > 0)
                                <a href="{{ route('search.index', $query ? ['q' => $query] : []) }}" class="text-xs text-red-400 font-semibold">Clear All</a>
                                @endif
                                <button @click="showFilters = false" class="text-gray-400 hover:text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-y-auto px-5 pb-8" style="max-height: calc(80vh - 70px);">

                        <!-- Sort -->
                        <div class="mb-5">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Sort By</p>
                            <div class="inline-flex bg-white/5 rounded-xl p-1 w-full">
                                <a href="{{ $urlFor(['sort' => 'latest']) }}" class="{{ $f['sort'] == 'latest' ? 'bg-white text-black' : 'text-gray-400' }} text-xs font-bold flex-1 text-center py-2 rounded-lg transition-colors">Latest</a>
                                <a href="{{ $urlFor(['sort' => 'popular']) }}" class="{{ $f['sort'] == 'popular' ? 'bg-white text-black' : 'text-gray-400' }} text-xs font-bold flex-1 text-center py-2 rounded-lg transition-colors">Popular</a>
                                <a href="{{ $urlFor(['sort' => 'rating']) }}" class="{{ $f['sort'] == 'rating' ? 'bg-white text-black' : 'text-gray-400' }} text-xs font-bold flex-1 text-center py-2 rounded-lg transition-colors">Top Rated</a>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div class="mb-5">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Category</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $urlFor(['category' => null]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ !$f['category'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">All</a>
                                @foreach($categories as $category)
                                    <a href="{{ $urlFor(['category' => $category->id]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $f['category'] == $category->id ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">{{ $category->name }}</a>
                                @endforeach
                            </div>
                        </div>

                        <!-- Genres -->
                        @if($genres->isNotEmpty())
                        <div class="mb-5">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Genre</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $urlFor(['genre' => null]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ !$f['genre'] ? 'bg-red-600/20 text-red-400 border border-red-600/30' : 'bg-white/5 text-gray-400' }}">All</a>
                                @foreach($genres as $genre)
                                    <a href="{{ $urlFor(['genre' => $genre->id]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $f['genre'] == $genre->id ? 'bg-red-600/20 text-red-400 border border-red-600/30' : 'bg-white/5 text-gray-400' }}">{{ $genre->name }}</a>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Language -->
                        <div class="mb-5">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Language</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $urlFor(['language' => null]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ !$f['language'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">All</a>
                                @foreach($languages as $language)
                                    <a href="{{ $urlFor(['language' => $language->id]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $f['language'] == $language->id ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">{{ $language->name }}</a>
                                @endforeach
                            </div>
                        </div>

                        <!-- Type -->
                        <div class="mb-5">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Type</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $urlFor(['type' => null]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ !$f['type'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">All</a>
                                <a href="{{ $urlFor(['type' => 'movie']) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $f['type'] == 'movie' ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">Movies</a>
                                <a href="{{ $urlFor(['type' => 'tv_show']) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $f['type'] == 'tv_show' ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">TV Shows</a>
                            </div>
                        </div>

                        <!-- Quality -->
                        @if($resolutions->isNotEmpty())
                        <div class="mb-5">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Quality</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $urlFor(['resolution' => null]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ !$f['resolution'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">All</a>
                                @foreach($resolutions as $resolution)
                                    <a href="{{ $urlFor(['resolution' => $resolution]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $f['resolution'] == $resolution ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">{{ $resolution }}</a>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Rating -->
                        @if($ratings->isNotEmpty())
                        <div class="mb-5">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Rating</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $urlFor(['rating' => null]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ !$f['rating'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">All</a>
                                @foreach($ratings as $rating)
                                    <a href="{{ $urlFor(['rating' => $rating]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $f['rating'] == $rating ? 'bg-primary text-white' : 'bg-white/5 text-gray-400' }}">{{ $rating }}</a>
                                @endforeach
                            </div>
                        </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════ --}}
            {{-- Results Grid                               --}}
            {{-- ═══════════════════════════════════════════ --}}
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

                <!-- Active filters pills (mobile) -->
                @if($activeCount > 0)
                <div class="md:hidden flex items-center gap-2 mb-3 overflow-x-auto no-scrollbar">
                    @if($f['category'])
                        <a href="{{ $urlFor(['category' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $categories->firstWhere('id', $f['category'])?->name ?? 'Category' }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['genre'])
                        <a href="{{ $urlFor(['genre' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-red-600/15 text-red-400 text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $genres->firstWhere('id', $f['genre'])?->name ?? 'Genre' }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['language'])
                        <a href="{{ $urlFor(['language' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $languages->firstWhere('id', $f['language'])?->name ?? 'Language' }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['type'])
                        <a href="{{ $urlFor(['type' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $f['type'] == 'movie' ? 'Movies' : 'TV Shows' }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['resolution'])
                        <a href="{{ $urlFor(['resolution' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $f['resolution'] }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['rating'])
                        <a href="{{ $urlFor(['rating' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $f['rating'] }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                </div>
                @endif

                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-base md:text-xl font-bold text-white">
                            @if($query)
                                Results for "<span class="text-primary">{{ $query }}</span>"
                            @else
                                Browse Videos
                            @endif
                        </h1>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ $videos->total() }} {{ Str::plural('video', $videos->total()) }}</p>
                    </div>

                    @if($activeCount > 0)
                        <a href="{{ route('search.index', $query ? ['q' => $query] : []) }}" class="hidden md:inline text-[11px] text-red-400 font-semibold hover:text-red-300">Clear {{ $activeCount }} {{ Str::plural('filter', $activeCount) }} ×</a>
                    @endif
                </div>

                @if($videos->count() > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4">
                        @foreach($videos as $video)
                            <x-video-card :video="$video" />
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $videos->links() }}
                    </div>
                @else
                    <div class="bg-card/40 border border-white/5 rounded-2xl p-10 text-center mt-4">
                        <div class="w-14 h-14 mx-auto rounded-full bg-white/5 flex items-center justify-center mb-3">
                            <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-1.5">No videos found</h3>
                        <p class="text-muted text-sm mb-5">Try a different search or clear some filters.</p>
                        <a href="{{ route('search.index') }}" class="inline-flex items-center bg-primary hover:bg-red-600 text-white font-bold text-xs px-5 py-2 rounded-xl transition-colors">Clear All Filters</a>
                    </div>
                @endif
            </div>

        </main>

        <x-mobile-nav />
    </body>
</html>
