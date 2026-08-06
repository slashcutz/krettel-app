@props(['video' => null])

@php
    $previews = data_get($video, 'previews', []);
    if (! is_array($previews)) {
        $previews = json_decode($previews, true) ?: [];
    }

    $candidates = array_values(array_filter($previews, fn ($u) => is_string($u) && trim($u) !== ''));

    if (empty($candidates)) {
        $candidates = array_values(array_filter([
            data_get($video, 'terabox_image'),
            data_get($video, 'thumbnail'),
            data_get($video, 'poster'),
        ]));
    }

    $image = null;
    if (count($candidates) === 1) {
        $image = $candidates[0];
    } elseif (count($candidates) > 1) {
        $image = $candidates[array_rand($candidates)];
    }

    $imageId = data_get($video, 'id');
    if ($image) {
        $image = \App\Support\TeraBoxImage::url($image, 'video', $imageId);
    }
    if (! $image) {
        $image = 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=1925&auto=format&fit=crop';
    }
@endphp

<a href="{{ route('video.show', data_get($video, 'slug', data_get($video, 'id', 'sample-video'))) }}" class="block group relative overflow-hidden rounded-md cursor-pointer transition-all duration-300 transform hover:scale-105 hover:z-50 hover:shadow-2xl bg-card border border-border/50 aspect-video">
    
    <!-- Thumbnail -->
    <img src="{{ $image }}" alt="Video Thumbnail" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
    
    <!-- Progress Bar (Primary Red) -->
    @php
        $cardProgress = data_get($video, 'progress');
        if (!$cardProgress && is_object($video)) {
            $cardProgress = data_get($video, 'id') ? (($video->id * 23) % 55 + 25) : null;
        }
    @endphp
    @if($cardProgress)
    <div class="absolute bottom-0 left-0 w-full h-1 bg-zinc-800/80 z-20 overflow-hidden">
        <div class="h-full bg-red-600" style="width: {{ $cardProgress }}%"></div>
    </div>
    @endif

    <!-- Hover Info Overlay -->
    <div class="absolute inset-0 bg-gradient-to-t from-background via-background/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4">
        <div class="flex items-center space-x-2 mb-2">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-black hover:bg-gray-200 transition-colors">
                <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </div>
            <div class="w-8 h-8 rounded-full border border-gray-400 bg-black/50 flex items-center justify-center text-white hover:border-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            <div class="w-8 h-8 rounded-full border border-gray-400 bg-black/50 flex items-center justify-center text-white hover:border-white transition-colors ml-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
        </div>
        
        <div class="flex items-center space-x-2 text-xs font-semibold mb-1">
            <span class="text-success">{{ data_get($video, 'match', 'New') }}</span>
            <span class="text-white border border-gray-500 px-1 rounded">{{ data_get($video, 'rating', 'TV-MA') }}</span>
            <span class="text-white">{{ data_get($video, 'duration', '1h 50m') }}</span>
            <span class="text-white border border-gray-500 px-1 rounded text-[10px]">{{ data_get($video, 'resolution', 'HD') }}</span>
        </div>
        
        <div class="text-white text-sm font-bold truncate">
            {{ data_get($video, 'title', 'Video Title') }}
        </div>
        <div class="text-gray-400 text-xs truncate mt-1">
            @if(is_object($video) && $video->category)
                {{ $video->category->name }}
            @else
                {{ data_get($video, 'categories', 'Action • Sci-Fi • Thriller') }}
            @endif
        </div>
    </div>
</a>
