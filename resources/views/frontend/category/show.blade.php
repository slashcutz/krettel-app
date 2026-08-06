<x-app-layout>
    <!-- Category Banner -->
    <div class="relative w-full h-64 md:h-80 bg-gray-900 overflow-hidden">
        @if($category->banner)
            <img src="{{ Storage::url($category->banner) }}" alt="{{ $category->name }}" class="w-full h-full object-cover opacity-60">
        @else
            <div class="absolute inset-0 bg-gradient-to-r from-primary/80 to-purple-900/80"></div>
        @endif
        
        <div class="absolute inset-0 bg-gradient-to-t from-background to-transparent"></div>
        
        <div class="absolute bottom-0 left-0 w-full p-8 md:p-12 max-w-7xl mx-auto flex items-end">
            <div>
                <h1 class="text-4xl md:text-6xl font-extrabold text-white tracking-tight drop-shadow-xl">{{ $category->name }}</h1>
                <p class="text-gray-300 mt-2 text-lg max-w-2xl">{{ $category->description ?? 'Explore our collection of amazing ' . strtolower($category->name) . ' content.' }}</p>
            </div>
        </div>
    </div>

    <!-- Video Grid -->
    <div class="pt-8 pb-12 max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
        
        <div class="flex justify-between items-center mb-8 border-b border-border pb-4">
            <h2 class="text-2xl font-bold text-white flex items-center">
                @if($category->icon)
                    <i class="{{ $category->icon }} mr-3 text-primary"></i>
                @endif
                Trending in {{ $category->name }}
            </h2>
            
            <div class="flex space-x-2">
                <select onchange="window.location.href = '{{ route('category.show', $category->slug) }}?sort=' + this.value" class="bg-card border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    <option value="latest" {{ request('sort') == 'latest' || !request('sort') ? 'selected' : '' }}>Sort by Latest</option>
                    <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Most Popular</option>
                    <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>Top Rated</option>
                </select>
            </div>
        </div>

        @if($videos->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                @foreach($videos as $video)
                    <x-video-card :video="$video" />
                @endforeach
            </div>
            
            <div class="mt-12">
                {{ $videos->links() }}
            </div>
        @else
            <div class="bg-card/50 border border-border rounded-xl p-16 text-center">
                <svg class="w-16 h-16 text-muted mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path></svg>
                <h3 class="text-2xl font-bold text-white mb-2">No videos available</h3>
                <p class="text-muted">There are currently no videos in this category. Check back later!</p>
            </div>
        @endif
    </div>
</x-app-layout>
