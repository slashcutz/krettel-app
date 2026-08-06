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
            
            /* Custom filter dropdown */
            .filter-dropdown {
                animation: filterSlideIn 0.2s ease-out;
            }
            @keyframes filterSlideIn {
                from { opacity: 0; transform: translateY(-6px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            /* Bottom sheet for mobile */
            .bottom-sheet-overlay {
                animation: overlayIn 0.2s ease-out;
            }
            @keyframes overlayIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .bottom-sheet-content {
                animation: sheetSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes sheetSlideUp {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }
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
        @endphp

        <x-sticky-header />

        <main class="min-h-screen pt-16 md:pt-20"
              x-data="{
                  searchQuery: '{{ addslashes($query) }}',
                  results: [],
                  loading: false,
                  debounceTimer: null,
                  showLive: false,
                  activeFilter: null,

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
                  openFilter(name) { this.activeFilter = this.activeFilter === name ? null : name; },
                  closeFilter() { this.activeFilter = null; }
              }"
              @click.away="closeFilter()"
              @keydown.escape.window="closeFilter()">

            <!-- Compact Search Bar -->
            <div class="sticky top-16 md:top-20 z-40 bg-background/95 backdrop-blur-xl border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 space-y-2.5">

                    <!-- Search Input -->
                    <div class="relative">
                        <form action="{{ route('search.index') }}" method="GET" class="relative">
                            <svg class="w-4 h-4 text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <input type="text" name="q" 
                                   x-model="searchQuery"
                                   @input="liveSearch()"
                                   @focus="if(results.length) showLive = true"
                                   @blur="closeLive()"
                                   placeholder="Search videos, genres..." 
                                   autocomplete="off"
                                   class="w-full bg-white/5 border border-white/10 text-white text-sm rounded-xl pl-10 pr-10 py-2.5 focus:ring-1 focus:ring-primary focus:border-primary placeholder-gray-500 transition-all">
                            <template x-if="searchQuery !== ''">
                                <button type="button" @click="searchQuery = ''; results = []; showLive = false;" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors" aria-label="Clear">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </template>
                        </form>

                        <!-- Live Search Dropdown -->
                        <div x-show="showLive && (results.length > 0 || loading)"
                             x-transition
                             class="absolute top-full left-0 right-0 mt-1.5 bg-zinc-900/98 backdrop-blur-xl border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                            <template x-if="loading && results.length === 0">
                                <div class="p-4 text-center">
                                    <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto"></div>
                                </div>
                            </template>
                            <template x-if="results.length > 0">
                                <div class="max-h-64 overflow-y-auto divide-y divide-white/5">
                                    <template x-for="video in results" :key="video.id">
                                        <a :href="`/watch/${video.slug}`" class="flex items-center gap-3 px-3 py-2.5 hover:bg-white/5 transition-colors">
                                            <div class="w-12 h-8 rounded-lg overflow-hidden flex-shrink-0 bg-zinc-800">
                                                <img :src="video.thumbnail" :alt="video.title" class="w-full h-full object-cover" loading="lazy">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-xs font-semibold text-white truncate" x-text="video.title"></h4>
                                                <span class="text-[10px] text-gray-500" x-text="(video.views || 0) + ' views'"></span>
                                            </div>
                                        </a>
                                    </template>
                                    <div class="px-3 py-2 text-center">
                                        <button type="button" @click="$el.closest('main').querySelector('form').submit()" class="text-[11px] text-primary font-bold hover:underline">View all results →</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Categories - horizontal scroll only -->
                    <div class="flex gap-1.5 overflow-x-auto no-scrollbar -mx-4 px-4">
                        <a href="{{ $urlFor(['category' => null]) }}" 
                           class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ !$f['category'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }}">All</a>
                        @foreach($categories as $category)
                            <a href="{{ $urlFor(['category' => $category->id]) }}" 
                               class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['category'] == $category->id ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }}">{{ $category->name }}</a>
                        @endforeach
                    </div>

                    <!-- Genres - horizontal scroll only -->
                    @if($genres->isNotEmpty())
                    <div class="flex gap-1.5 overflow-x-auto no-scrollbar -mx-4 px-4">
                        <a href="{{ $urlFor(['genre' => null]) }}" 
                           class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ !$f['genre'] ? 'bg-red-600/20 text-red-400 border border-red-600/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">All Genres</a>
                        @foreach($genres as $genre)
                            <a href="{{ $urlFor(['genre' => $genre->id]) }}" 
                               class="flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['genre'] == $genre->id ? 'bg-red-600/20 text-red-400 border border-red-600/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">{{ $genre->name }}</a>
                        @endforeach
                    </div>
                    @endif

                    <!-- Compact Filter Row + Sort -->
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar -mx-4 px-4">
                        <!-- Custom Language Filter -->
                        <div class="relative flex-shrink-0" @click.outside="if(activeFilter==='language') closeFilter()">
                            <button @click="openFilter('language')" 
                                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['language'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path></svg>
                                {{ $f['language'] ? $languages->firstWhere('id', $f['language'])?->name ?? 'Language' : 'Language' }}
                                <svg class="w-2.5 h-2.5 ml-0.5 transition-transform" :class="activeFilter === 'language' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <!-- Desktop dropdown -->
                            <div x-show="activeFilter === 'language'" x-transition class="filter-dropdown hidden md:block absolute top-full left-0 mt-1.5 w-44 bg-zinc-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                <div class="max-h-52 overflow-y-auto py-1">
                                    <a href="{{ $urlFor(['language' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['language'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">All Languages</a>
                                    @foreach($languages as $language)
                                        <a href="{{ $urlFor(['language' => $language->id]) }}" class="block px-3 py-2 text-xs {{ $f['language'] == $language->id ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">{{ $language->name }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Custom Type Filter -->
                        <div class="relative flex-shrink-0" @click.outside="if(activeFilter==='type') closeFilter()">
                            <button @click="openFilter('type')" 
                                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['type'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path></svg>
                                {{ $f['type'] == 'movie' ? 'Movies' : ($f['type'] == 'tv_show' ? 'TV Shows' : 'Type') }}
                                <svg class="w-2.5 h-2.5 ml-0.5 transition-transform" :class="activeFilter === 'type' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div x-show="activeFilter === 'type'" x-transition class="filter-dropdown hidden md:block absolute top-full left-0 mt-1.5 w-36 bg-zinc-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                <div class="py-1">
                                    <a href="{{ $urlFor(['type' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['type'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">All Types</a>
                                    <a href="{{ $urlFor(['type' => 'movie']) }}" class="block px-3 py-2 text-xs {{ $f['type'] == 'movie' ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">Movies</a>
                                    <a href="{{ $urlFor(['type' => 'tv_show']) }}" class="block px-3 py-2 text-xs {{ $f['type'] == 'tv_show' ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">TV Shows</a>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Quality Filter -->
                        @if($resolutions->isNotEmpty())
                        <div class="relative flex-shrink-0" @click.outside="if(activeFilter==='quality') closeFilter()">
                            <button @click="openFilter('quality')" 
                                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['resolution'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                {{ $f['resolution'] ?: 'Quality' }}
                                <svg class="w-2.5 h-2.5 ml-0.5 transition-transform" :class="activeFilter === 'quality' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div x-show="activeFilter === 'quality'" x-transition class="filter-dropdown hidden md:block absolute top-full left-0 mt-1.5 w-36 bg-zinc-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                <div class="py-1">
                                    <a href="{{ $urlFor(['resolution' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['resolution'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">All Quality</a>
                                    @foreach($resolutions as $resolution)
                                        <a href="{{ $urlFor(['resolution' => $resolution]) }}" class="block px-3 py-2 text-xs {{ $f['resolution'] == $resolution ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">{{ $resolution }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Custom Rating Filter -->
                        @if($ratings->isNotEmpty())
                        <div class="relative flex-shrink-0" @click.outside="if(activeFilter==='rating') closeFilter()">
                            <button @click="openFilter('rating')" 
                                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-colors {{ $f['rating'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-gray-400 hover:text-white' }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                                {{ $f['rating'] ?: 'Rating' }}
                                <svg class="w-2.5 h-2.5 ml-0.5 transition-transform" :class="activeFilter === 'rating' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div x-show="activeFilter === 'rating'" x-transition class="filter-dropdown hidden md:block absolute top-full left-0 mt-1.5 w-36 bg-zinc-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                <div class="py-1">
                                    <a href="{{ $urlFor(['rating' => null]) }}" class="block px-3 py-2 text-xs {{ !$f['rating'] ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">All Ratings</a>
                                    @foreach($ratings as $rating)
                                        <a href="{{ $urlFor(['rating' => $rating]) }}" class="block px-3 py-2 text-xs {{ $f['rating'] == $rating ? 'text-primary font-bold bg-primary/10' : 'text-gray-300 hover:bg-white/5' }} transition-colors">{{ $rating }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Spacer -->
                        <div class="flex-1"></div>

                        <!-- Sort Toggle - compact -->
                        <div class="flex-shrink-0 inline-flex bg-white/5 rounded-lg p-0.5">
                            <a href="{{ $urlFor(['sort' => 'latest']) }}" class="{{ $f['sort'] == 'latest' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[10px] font-bold px-2.5 py-1 rounded-md transition-colors">Latest</a>
                            <a href="{{ $urlFor(['sort' => 'popular']) }}" class="{{ $f['sort'] == 'popular' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[10px] font-bold px-2.5 py-1 rounded-md transition-colors">Popular</a>
                            <a href="{{ $urlFor(['sort' => 'rating']) }}" class="{{ $f['sort'] == 'rating' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[10px] font-bold px-2.5 py-1 rounded-md transition-colors">Top</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Bottom Sheet for Filters -->
            <template x-if="activeFilter !== null">
                <div class="md:hidden fixed inset-0 z-50" @click.self="closeFilter()">
                    <div class="bottom-sheet-overlay absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeFilter()"></div>
                    <div class="bottom-sheet-content absolute bottom-0 left-0 right-0 bg-zinc-900 border-t border-white/10 rounded-t-2xl max-h-[60vh] overflow-hidden">
                        <!-- Handle -->
                        <div class="flex justify-center pt-3 pb-2">
                            <div class="w-10 h-1 bg-white/20 rounded-full"></div>
                        </div>
                        
                        <!-- Title -->
                        <div class="px-4 pb-3 border-b border-white/5">
                            <h3 class="text-sm font-bold text-white" x-text="
                                activeFilter === 'language' ? 'Select Language' :
                                activeFilter === 'type' ? 'Select Type' :
                                activeFilter === 'quality' ? 'Select Quality' :
                                activeFilter === 'rating' ? 'Select Rating' : ''
                            "></h3>
                        </div>

                        <!-- Options -->
                        <div class="overflow-y-auto max-h-[45vh] pb-8">
                            <!-- Language Options -->
                            <template x-if="activeFilter === 'language'">
                                <div class="py-1">
                                    <a href="{{ $urlFor(['language' => null]) }}" class="flex items-center justify-between px-4 py-3 {{ !$f['language'] ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                        <span class="text-sm">All Languages</span>
                                        @if(!$f['language'])<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                    </a>
                                    @foreach($languages as $language)
                                        <a href="{{ $urlFor(['language' => $language->id]) }}" class="flex items-center justify-between px-4 py-3 {{ $f['language'] == $language->id ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                            <span class="text-sm">{{ $language->name }}</span>
                                            @if($f['language'] == $language->id)<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </template>

                            <!-- Type Options -->
                            <template x-if="activeFilter === 'type'">
                                <div class="py-1">
                                    <a href="{{ $urlFor(['type' => null]) }}" class="flex items-center justify-between px-4 py-3 {{ !$f['type'] ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                        <span class="text-sm">All Types</span>
                                        @if(!$f['type'])<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                    </a>
                                    <a href="{{ $urlFor(['type' => 'movie']) }}" class="flex items-center justify-between px-4 py-3 {{ $f['type'] == 'movie' ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                        <span class="text-sm">Movies</span>
                                        @if($f['type'] == 'movie')<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                    </a>
                                    <a href="{{ $urlFor(['type' => 'tv_show']) }}" class="flex items-center justify-between px-4 py-3 {{ $f['type'] == 'tv_show' ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                        <span class="text-sm">TV Shows</span>
                                        @if($f['type'] == 'tv_show')<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                    </a>
                                </div>
                            </template>

                            <!-- Quality Options -->
                            <template x-if="activeFilter === 'quality'">
                                <div class="py-1">
                                    <a href="{{ $urlFor(['resolution' => null]) }}" class="flex items-center justify-between px-4 py-3 {{ !$f['resolution'] ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                        <span class="text-sm">All Quality</span>
                                        @if(!$f['resolution'])<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                    </a>
                                    @foreach($resolutions as $resolution)
                                        <a href="{{ $urlFor(['resolution' => $resolution]) }}" class="flex items-center justify-between px-4 py-3 {{ $f['resolution'] == $resolution ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                            <span class="text-sm">{{ $resolution }}</span>
                                            @if($f['resolution'] == $resolution)<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </template>

                            <!-- Rating Options -->
                            <template x-if="activeFilter === 'rating'">
                                <div class="py-1">
                                    <a href="{{ $urlFor(['rating' => null]) }}" class="flex items-center justify-between px-4 py-3 {{ !$f['rating'] ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                        <span class="text-sm">All Ratings</span>
                                        @if(!$f['rating'])<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                    </a>
                                    @foreach($ratings as $rating)
                                        <a href="{{ $urlFor(['rating' => $rating]) }}" class="flex items-center justify-between px-4 py-3 {{ $f['rating'] == $rating ? 'text-primary' : 'text-gray-300' }} hover:bg-white/5 transition-colors">
                                            <span class="text-sm">{{ $rating }}</span>
                                            @if($f['rating'] == $rating)<svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Results -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

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

                    <!-- Active filters summary -->
                    @php
                        $activeFilters = collect($f)->filter(fn($v, $k) => $v && $k !== 'sort')->count();
                    @endphp
                    @if($activeFilters > 0)
                        <a href="{{ route('search.index', $query ? ['q' => $query] : []) }}" class="text-[11px] text-red-400 font-semibold hover:text-red-300 transition-colors">
                            Clear {{ $activeFilters }} {{ Str::plural('filter', $activeFilters) }} ×
                        </a>
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
                        <a href="{{ route('search.index') }}" class="inline-flex items-center bg-primary hover:bg-red-600 text-white font-bold text-xs px-5 py-2 rounded-xl transition-colors">
                            Clear All Filters
                        </a>
                    </div>
                @endif
            </div>

        </main>

        <x-mobile-nav />
    </body>
</html>
