<header x-data="headerSearch()" @scroll.window="scrolled = (window.pageYOffset > 20)" @keydown.escape.window="closeSearch()"
        :class="{ 'bg-background/95 backdrop-blur-md shadow-md': scrolled || open, 'bg-transparent': !scrolled && !open }"
        class="fixed top-0 w-full z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20 md:h-28 items-center">

            <!-- Expanded Search (takes over the navbar row) -->
            <div x-show="open" x-cloak x-transition class="flex-1 flex items-center gap-3 w-full">
                <button @click="closeSearch()" class="w-10 h-10 -ml-2 flex-shrink-0 flex items-center justify-center rounded-full hover:bg-white/10 transition-colors text-white" aria-label="Close search">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <div class="relative flex-1">
                    <svg class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input x-ref="input" type="text" x-model="q" @input="onInput()" @keydown.enter.prevent="submitSearch()"
                           placeholder="Search videos, categories, collections..." autocomplete="off"
                           class="w-full bg-white/5 border border-white/15 text-white text-sm rounded-2xl pl-11 pr-20 py-3 focus:ring-primary focus:border-primary placeholder-gray-500">
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                        <button x-show="q" @click="q=''; $refs.input.focus();" class="w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-white transition-colors" aria-label="Clear">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        <button @click="submitSearch()" class="w-8 h-8 flex items-center justify-center rounded-full bg-primary text-white hover:bg-red-600 transition-colors" aria-label="Submit search">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Logo -->
            <div x-show="!open" class="flex-shrink-0 flex items-center overflow-visible">
                <a href="{{ Auth::check() ? route('dashboard') : route('home') }}" class="flex items-center -ml-2">
                    <img src="{{ \App\Models\Setting::get('navbar_logo') ?: asset('images/logo.png') }}" alt="Krettel" class="h-20 sm:h-28 md:h-40 w-auto object-contain drop-shadow-lg scale-110 md:scale-125 transform origin-left">
                </a>
            </div>

            <!-- Navigation Links -->
            <nav x-show="!open" class="hidden md:flex items-center space-x-6 ml-10">
                <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'text-white font-semibold' : 'text-muted hover:text-white' }} transition-colors font-medium">Home</a>
                <a href="{{ route('collections.index') }}" class="{{ request()->routeIs('collections.*') || request()->routeIs('collection.*') ? 'text-white font-semibold' : 'text-muted hover:text-white' }} transition-colors font-medium">Collections</a>
                <a href="{{ route('search.index') }}" class="{{ request()->routeIs('search.*') || request()->routeIs('category.*') ? 'text-white font-semibold' : 'text-muted hover:text-white' }} transition-colors font-medium">Categories</a>
                <a href="{{ route('my-list') }}" class="{{ request()->routeIs('my-list') || request()->routeIs('dashboard') ? 'text-white font-semibold' : 'text-muted hover:text-white' }} transition-colors font-medium">My List</a>
            </nav>

            <!-- Actions -->
            <div x-show="!open" class="flex items-center space-x-4">
                <button @click="openSearch()" class="text-white hover:text-primary transition-colors" aria-label="Open search">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
                @auth
                <a href="{{ route('profile.edit') }}" class="h-8 w-8 rounded-full bg-secondary overflow-hidden border border-border" title="Profile">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'User') }}&background=111827&color=EF4444" alt="Profile">
                </a>
                @else
                <a href="{{ route('login') }}" class="flex items-center space-x-1.5 text-white text-sm font-medium hover:text-primary transition-colors" title="Log in">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-9 9v2a2 2 0 002 2h4a2 2 0 002-2V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"></path></svg>
                    <span class="hidden sm:inline">Log in</span>
                </a>
                @endauth
            </div>
        </div>
    </div>

    <!-- Live Suggestions Dropdown -->
    <div x-show="open" x-cloak x-transition class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-4">
        <div class="bg-card border border-white/10 rounded-2xl shadow-2xl overflow-hidden max-h-[65vh] overflow-y-auto no-scrollbar">
            <template x-if="loading">
                <div class="p-6 text-center text-gray-400 text-sm flex items-center justify-center gap-2">
                    <svg class="w-5 h-5 animate-spin text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Searching...
                </div>
            </template>

            <template x-if="!loading && q.trim().length > 0 && totalCount === 0">
                <div class="p-6 text-center text-gray-400 text-sm">
                    No results found for "<span x-text="q" class="text-white"></span>"
                </div>
            </template>

            <!-- Videos -->
            <template x-if="results.videos.length > 0">
                <div>
                    <div class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-white/5">Videos</div>
                    <template x-for="v in results.videos" :key="v.id">
                        <a :href="'/watch/' + v.slug" class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 transition-colors">
                            <img :src="v.thumbnail" alt="" class="w-14 h-9 object-cover rounded-lg flex-shrink-0 bg-secondary">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white truncate" x-text="v.title"></p>
                                <p class="text-xs text-gray-500" x-text="(v.views ? v.views.toLocaleString() + ' views' : '') + (v.resolution ? ' · ' + v.resolution : '')"></p>
                            </div>
                            <svg class="w-4 h-4 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </template>
                </div>
            </template>

            <!-- Categories -->
            <template x-if="results.categories.length > 0">
                <div>
                    <div class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-white/5">Categories</div>
                    <template x-for="c in results.categories" :key="c.id">
                        <a :href="'/category/' + c.slug" class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 transition-colors">
                            <div class="w-9 h-9 rounded-full bg-secondary border border-white/10 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white truncate" x-text="c.name"></p>
                                <p class="text-xs text-gray-500">Category</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </template>
                </div>
            </template>

            <!-- Collections -->
            <template x-if="results.collections.length > 0">
                <div>
                    <div class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-white/5">Collections</div>
                    <template x-for="c in results.collections" :key="c.id">
                        <a :href="'/collection/' + c.slug" class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 transition-colors">
                            <img :src="c.image" alt="" class="w-9 h-9 object-cover rounded-full flex-shrink-0 bg-secondary">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white truncate" x-text="c.name"></p>
                                <p class="text-xs text-gray-500">Collection</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>
</header>
