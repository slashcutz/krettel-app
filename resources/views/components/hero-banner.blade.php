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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 sm:pt-28 md:pt-32">
    <section class="relative w-full rounded-3xl overflow-hidden bg-zinc-900 flex flex-col justify-end p-6 md:p-10 border border-zinc-800/60 shadow-2xl group" style="aspect-ratio: 16/9;">
        <!-- Hero Background Image -->
        <img src="{{ $image }}" alt="Hero Background" loading="eager" fetchpriority="high" decoding="sync" class="absolute inset-0 w-full h-full object-cover z-0">
        
        <!-- Gradient Overlays for High Contrast Readability -->
        <div class="absolute inset-0 bg-gradient-to-t from-[#08080A] via-[#08080A]/50 to-transparent z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-[#08080A]/80 via-transparent to-transparent z-10"></div>

        <!-- Content -->
        <div class="relative z-20 space-y-3 max-w-xl text-left">
            @if($isBanner)
            <span class="text-xs font-extrabold uppercase tracking-widest text-red-600">Featured</span>
            @else
            <span class="text-xs font-extrabold uppercase tracking-widest text-red-600">Now Streaming</span>
            @endif
            
            <h1 class="text-2xl sm:text-4xl lg:text-5xl font-black tracking-tight text-white drop-shadow-md">
                {{ $title }}
            </h1>
            
            <!-- Metadata Row -->
            <div class="flex items-center space-x-2.5 text-xs font-semibold text-zinc-300 flex-wrap gap-y-1">
                @if($match)<span class="text-emerald-400 font-bold">{{ $match }}</span>@endif
                @if($rating)<span class="bg-zinc-800/80 border border-zinc-700 px-2 py-0.5 rounded text-[11px] text-zinc-200">{{ $rating }}</span>@endif
                @if($year)<span>{{ $year }}</span>@endif
                @if($duration)
                    <span>{{ gmdate('H:i', $duration) }}</span>
                @endif
                @if($quality)<span class="bg-zinc-800/80 border border-zinc-700 px-1.5 py-0.5 rounded text-[10px] text-zinc-200 font-bold">{{ $quality }}</span>@endif
            </div>

            @if($description)
            <p class="text-sm md:text-base text-zinc-300 line-clamp-2 leading-relaxed max-w-lg drop-shadow-sm">
                {{ $description }}
            </p>
            @endif

            <!-- Action Buttons -->
            @if($slug)
            <div class="flex items-center space-x-3 pt-2">
                <a href="{{ route('video.show', $slug) }}" class="bg-netflix-red hover:bg-red-700 text-white font-bold px-6 py-3 rounded-xl flex items-center space-x-2 shadow-lg active:scale-95 transition text-xs sm:text-sm">
                    <svg class="w-4 h-4 fill-white text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <span>Play</span>
                </a>
                <a href="{{ route('video.show', $slug) }}" class="bg-zinc-900/80 hover:bg-zinc-800/90 border border-zinc-700/80 text-white font-semibold px-5 py-3 rounded-xl flex items-center space-x-2 backdrop-blur-md active:scale-95 transition text-xs sm:text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>More Info</span>
                </a>
            </div>
            @endif
        </div>
    </section>
</div>