@props(['video' => null])

@php
    $isBanner = $video instanceof \App\Models\Banner;
    $isVideo = $video instanceof \App\Models\Video;

    $bannerVideo = $isBanner ? optional($video->video) : $video;
    $title = $isBanner ? $video->title : data_get($bannerVideo, 'title', 'Featured Title');
    $description = $isBanner ? ($video->subtitle ?? '') : data_get($bannerVideo, 'short_description', data_get($bannerVideo, 'full_description', ''));
    $image = $isBanner ? $video->image_url : data_get($bannerVideo, 'thumbnail', '');
    $slug = data_get($bannerVideo, 'slug');
    $year = $isBanner ? optional($video->created_at)->format('Y') : data_get($bannerVideo, 'release_year', '');
    $quality = data_get($bannerVideo, 'resolution', 'HD');
    $rating = data_get($bannerVideo, 'age_rating', '');
    $duration = data_get($bannerVideo, 'duration', '');
    $match = data_get($bannerVideo, 'match', '98% Match');

    if ($image === '') {
        $image = 'https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?q=80&w=2070&auto=format&fit=crop';
    }
@endphp

<div class="relative w-full h-[80vh] md:h-[90vh] flex items-center justify-start overflow-hidden">
    <!-- Background Video/Image -->
    <div class="absolute inset-0 w-full h-full">
        <img src="{{ $image }}" alt="Hero Background" loading="eager" fetchpriority="high" decoding="sync" class="w-full h-full object-cover">
        
        <!-- Gradient Overlays for smooth transition to background -->
        <div class="absolute inset-0 bg-gradient-to-r from-background via-background/60 to-transparent"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent"></div>
    </div>

    <!-- Content -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="max-w-2xl">
            <h1 class="text-3xl sm:text-4xl lg:text-6xl font-bold text-white mb-4 tracking-tight drop-shadow-lg">
                {{ $title }}
            </h1>
            
            <div class="flex items-center space-x-4 mb-6 text-sm md:text-base font-medium">
                @if($match)<span class="text-success">{{ $match }}</span>@endif
                @if($rating)<span class="text-white border border-white/40 px-2 py-0.5 rounded">{{ $rating }}</span>@endif
                @if($year)<span class="text-white">{{ $year }}</span>@endif
                @if($duration)
                    <span class="text-white">{{ gmdate('H:i', $duration) }}</span>
                @endif
                @if($quality)<span class="text-white bg-white/20 px-2 py-0.5 rounded">{{ $quality }}</span>@endif
            </div>

            @if($description)
            <p class="text-lg text-white/90 mb-8 line-clamp-3 drop-shadow-md leading-relaxed">
                {{ $description }}
            </p>
            @endif

            @if($slug)
            <div class="flex items-center space-x-4">
                <a href="{{ route('video.show', $slug) }}" class="flex items-center justify-center px-8 py-3 bg-white text-black rounded-lg font-bold hover:bg-white/80 transition-colors">
                    <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    Play
                </a>
                <a href="{{ route('video.show', $slug) }}" class="flex items-center justify-center px-8 py-3 bg-gray-500/50 text-white rounded-lg font-bold hover:bg-gray-500/70 transition-colors backdrop-blur-sm">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    More Info
                </a>
            </div>
            @endif
        </div>
    </div>
</div>