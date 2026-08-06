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
            
            /* Animation for popup modal */
            .modal-content {
                animation: modalScaleUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes modalScaleUp {
                from { opacity: 0; transform: scale(0.95); }
                to { opacity: 1; transform: scale(1); }
            }
            
            /* Bottom sheet animation for mobile */
            .bottom-sheet-content {
                animation: sheetUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes sheetUp {
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
                  closeLive() { setTimeout(() => { this.showLive = false; }, 200); }
              }"
              @keydown.escape.window="showFilters = false">

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- STICKY HEADER ROW: Search & Filter Trigger Button  --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            <div class="sticky top-16 md:top-20 z-40 bg-background/95 backdrop-blur-xl border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">

                    <div class="flex items-center gap-3">
                        <!-- Search Input -->
                        <div class="relative flex-1">
                            <form action="{{ route('search.index') }}" method="GET" class="relative">
                                <svg class="w-4 h-4 text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" name="q" 
                                       x-model="searchQuery"
                                       @input="liveSearch()"
                                       @focus="if(results.length) showLive = true"
                                       @blur="closeLive()"
                                       placeholder="Search videos, categories, tags..." 
                                       autocomplete="off"
                                       class="w-full bg-white/5 border border-white/8 text-white text-sm rounded-xl pl-10 pr-9 py-2 focus:ring-1 focus:ring-primary focus:border-primary placeholder-gray-500 transition-all">
                                <template x-if="searchQuery !== ''">
                                    <button type="button" @click="searchQuery = ''; results = []; showLive = false;" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </template>
                            </form>

                            <!-- Live Dropdown -->
                            <div x-show="showLive && (results.length > 0 || loading)" x-transition
                                 class="absolute top-full left-0 right-0 mt-1.5 bg-zinc-900/98 backdrop-blur-xl border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                                <template x-if="loading && results.length === 0">
                                    <div class="p-3.5 text-center"><div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto"></div></div>
                                </template>
                                <template x-if="results.length > 0">
                                    <div class="max-h-60 overflow-y-auto divide-y divide-white/5">
                                        <template x-for="video in results" :key="video.id">
                                            <a :href="`/watch/${video.slug}`" class="flex items-center gap-3 px-3.5 py-2.5 hover:bg-white/5 transition-colors">
                                                <div class="w-12 h-8 rounded-lg overflow-hidden flex-shrink-0 bg-zinc-800">
                                                    <img :src="video.thumbnail" :alt="video.title" class="w-full h-full object-cover" loading="lazy">
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="text-xs font-semibold text-white truncate" x-text="video.title"></h4>
                                                    <span class="text-[10px] text-gray-500" x-text="(video.views || 0) + ' views'"></span>
                                                </div>
                                            </a>
                                        </template>
                                        <div class="px-3.5 py-2 text-center">
                                            <button type="button" @click="$el.closest('main').querySelector('form').submit()" class="text-xs text-primary font-bold hover:underline">View all results →</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Filter Button (Opens modal/sheet) -->
                        <button @click="showFilters = true" 
                                class="relative flex-shrink-0 h-9 px-4 rounded-xl bg-white/5 border border-white/8 flex items-center justify-center gap-2 text-gray-300 hover:text-white hover:bg-white/10 transition-all font-semibold text-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                            <span>Filters</span>
                            @if($activeCount > 0)
                            <span class="w-4 h-4 bg-primary text-white text-[9px] font-bold rounded-full flex items-center justify-center">{{ $activeCount }}</span>
                            @endif
                        </button>

                        <!-- Sort Toggle (Always visible in header) -->
                        <div class="inline-flex flex-shrink-0 bg-white/5 rounded-xl p-0.5 border border-white/5">
                            <a href="{{ $urlFor(['sort' => 'latest']) }}" class="{{ $f['sort'] == 'latest' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[11px] font-bold px-3 py-1.5 rounded-lg transition-colors">Latest</a>
                            <a href="{{ $urlFor(['sort' => 'popular']) }}" class="{{ $f['sort'] == 'popular' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[11px] font-bold px-3 py-1.5 rounded-lg transition-colors">Popular</a>
                            <a href="{{ $urlFor(['sort' => 'rating']) }}" class="{{ $f['sort'] == 'rating' ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }} text-[11px] font-bold px-3 py-1.5 rounded-lg transition-colors">Top Rated</a>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            {{-- FILTER MODAL / BOTTOM SHEET: Responsive for Mobile, Tablet & Desktop --}}
            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            <div x-show="showFilters" x-cloak class="fixed inset-0 z-50 flex items-end md:items-center justify-center p-0 md:p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" 
                     @click="showFilters = false" 
                     x-show="showFilters" 
                     x-transition:enter="transition ease-out duration-200" 
                     x-transition:enter-start="opacity-0" 
                     x-transition:enter-end="opacity-100" 
                     x-transition:leave="transition ease-in duration-150" 
                     x-transition:leave-start="opacity-100" 
                     x-transition:leave-end="opacity-0"></div>

                <!-- Modal Window -->
                <div class="bottom-sheet-content md:modal-content relative w-full md:max-w-2xl bg-zinc-900 border border-white/10 rounded-t-2xl md:rounded-2xl overflow-hidden shadow-2xl flex flex-col z-10 max-h-[85vh] md:max-h-[80vh]">
                    
                    <!-- Header -->
                    <div class="sticky top-0 bg-zinc-900 border-b border-white/5 px-5 py-4 flex items-center justify-between z-10">
                        <div>
                            <h3 class="text-base font-bold text-white">Filter Videos</h3>
                            <p class="text-[10px] text-gray-500 mt-0.5">Select options to narrow down your results</p>
                        </div>
                        <div class="flex items-center gap-3.5">
                            @if($activeCount > 0)
                            <a href="{{ route('search.index', $query ? ['q' => $query] : []) }}" class="text-xs text-red-400 font-bold hover:text-red-300">Clear All</a>
                            @endif
                            <button @click="showFilters = false" class="w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Filter Sections Content (Scrollable Grid) -->
                    <div class="overflow-y-auto p-6 space-y-6 flex-1">
                        
                        <!-- Categories Grid -->
                        <div>
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2.5">Category</p>
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                <a href="{{ $urlFor(['category' => null]) }}" class="px-3 py-2 rounded-xl text-xs font-semibold text-center border transition-all {{ !$f['category'] ? 'bg-primary border-primary text-white font-bold shadow-lg shadow-primary/20' : 'bg-white/5 border-white/5 text-gray-400 hover:text-white hover:bg-white/10' }}">All</a>
                                @foreach($categories as $category)
                                    <a href="{{ $urlFor(['category' => $category->id]) }}" class="px-3 py-2 rounded-xl text-xs font-semibold text-center border transition-all {{ $f['category'] == $category->id ? 'bg-primary border-primary text-white font-bold shadow-lg shadow-primary/20' : 'bg-white/5 border-white/5 text-gray-400 hover:text-white hover:bg-white/10' }}">{{ $category->name }}</a>
                                @endforeach
                            </div>
                        </div>

                        <!-- Genres Grid -->
                        @if($genres->isNotEmpty())
                        <div>
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2.5">Genre</p>
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                <a href="{{ $urlFor(['genre' => null]) }}" class="px-3 py-2 rounded-xl text-xs font-semibold text-center border transition-all {{ !$f['genre'] ? 'bg-red-600 border-red-600 text-white font-bold shadow-lg shadow-red-600/20' : 'bg-white/5 border-white/5 text-gray-400 hover:text-white hover:bg-white/10' }}">All</a>
                                @foreach($genres as $genre)
                                    <a href="{{ $urlFor(['genre' => $genre->id]) }}" class="px-3 py-2 rounded-xl text-xs font-semibold text-center border transition-all {{ $f['genre'] == $genre->id ? 'bg-red-600 border-red-600 text-white font-bold shadow-lg shadow-red-600/20' : 'bg-white/5 border-white/5 text-gray-400 hover:text-white hover:bg-white/10' }}">{{ $genre->name }}</a>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Dropdowns Grid for Language, Type, Quality, Rating -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-white/5 pt-5">
                            
                            <!-- Language Selection -->
                            <div>
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Language</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <a href="{{ $urlFor(['language' => null]) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ !$f['language'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">All</a>
                                    @foreach($languages as $language)
                                        <a href="{{ $urlFor(['language' => $language->id]) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $f['language'] == $language->id ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">{{ $language->name }}</a>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Type Selection -->
                            <div>
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Type</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <a href="{{ $urlFor(['type' => null]) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ !$f['type'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">All</a>
                                    <a href="{{ $urlFor(['type' => 'movie']) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $f['type'] == 'movie' ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">Movies</a>
                                    <a href="{{ $urlFor(['type' => 'tv_show']) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $f['type'] == 'tv_show' ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">TV Shows</a>
                                </div>
                            </div>

                            <!-- Quality Selection -->
                            @if($resolutions->isNotEmpty())
                            <div class="sm:col-span-2 border-t border-white/5 pt-4">
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Quality</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <a href="{{ $urlFor(['resolution' => null]) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ !$f['resolution'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">All</a>
                                    @foreach($resolutions as $resolution)
                                        <a href="{{ $urlFor(['resolution' => $resolution]) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $f['resolution'] == $resolution ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">{{ $resolution }}</a>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            <!-- Rating Selection -->
                            @if($ratings->isNotEmpty())
                            <div class="sm:col-span-2 border-t border-white/5 pt-4">
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mb-2">Rating</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <a href="{{ $urlFor(['rating' => null]) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ !$f['rating'] ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">All</a>
                                    @foreach($ratings as $rating)
                                        <a href="{{ $urlFor(['rating' => $rating]) }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $f['rating'] == $rating ? 'bg-primary text-white' : 'bg-white/5 text-gray-400 hover:text-white' }} transition-colors">{{ $rating }}</a>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="p-4 bg-zinc-950 border-t border-white/5 flex items-center justify-end">
                        <button @click="showFilters = false" class="px-6 py-2 bg-primary hover:bg-red-600 text-white font-bold text-xs rounded-xl transition-colors shadow-lg shadow-primary/10">Apply Filters</button>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════ --}}
            {{-- Results Grid                               --}}
            {{-- ═══════════════════════════════════════════ --}}
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

                <!-- Active filters pills (removable chips) -->
                @if($activeCount > 0)
                <div class="flex items-center gap-2 mb-3.5 overflow-x-auto no-scrollbar">
                    @if($f['category'])
                        <a href="{{ $urlFor(['category' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[10px] md:text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $categories->firstWhere('id', $f['category'])?->name ?? 'Category' }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['genre'])
                        <a href="{{ $urlFor(['genre' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-red-600/15 text-red-400 text-[10px] md:text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $genres->firstWhere('id', $f['genre'])?->name ?? 'Genre' }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['language'])
                        <a href="{{ $urlFor(['language' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[10px] md:text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $languages->firstWhere('id', $f['language'])?->name ?? 'Language' }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['type'])
                        <a href="{{ $urlFor(['type' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[10px] md:text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $f['type'] == 'movie' ? 'Movies' : 'TV Shows' }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['resolution'])
                        <a href="{{ $urlFor(['resolution' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[10px] md:text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $f['resolution'] }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                    @if($f['rating'])
                        <a href="{{ $urlFor(['rating' => null]) }}" class="flex-shrink-0 inline-flex items-center gap-1 bg-primary/15 text-primary text-[10px] md:text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                            {{ $f['rating'] }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                </div>
                @endif

                <!-- Title & Meta -->
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
