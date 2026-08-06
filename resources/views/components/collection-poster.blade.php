@props(['collection' => null])

@php
    $image = data_get($collection, 'terabox_image') ?: data_get($collection, 'image');
    $collectionId = data_get($collection, 'id');
    $imageUrl = $image ? \App\Support\TeraBoxImage::url($image, 'collection', $collectionId) : null;

    $imageUrl = $imageUrl ?: 'https://via.placeholder.com/400x600?text=' . urlencode(data_get($collection, 'name', 'Collection'));
    $count = (int) data_get($collection, 'items_count', data_get($collection, 'videos_count', 0));
@endphp

<a href="{{ route('collection.show', data_get($collection, 'slug', data_get($collection, 'id', 'sample-collection'))) }}"
   class="group relative block aspect-[2/3] rounded-2xl overflow-hidden border border-white/10 bg-secondary shadow-lg transition-all duration-300 hover:scale-[1.03] hover:border-primary hover:shadow-2xl">
    <img src="{{ $imageUrl }}" alt="{{ data_get($collection, 'name', 'Collection') }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">

    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>

    <div class="absolute top-3 right-3 flex items-center gap-1.5 bg-black/60 backdrop-blur-md border border-white/15 text-white text-[11px] font-semibold px-2.5 py-1 rounded-full">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
        {{ $count }} {{ Str::plural('video', $count) }}
    </div>

    <div class="absolute inset-x-0 bottom-0 p-4">
        <h3 class="text-white font-bold text-sm sm:text-base truncate drop-shadow-lg">{{ data_get($collection, 'name', 'Collection') }}</h3>
        @if(data_get($collection, 'description'))
            <p class="text-gray-300 text-xs mt-1 line-clamp-2">{{ data_get($collection, 'description') }}</p>
        @endif
        <span class="inline-flex items-center mt-3 text-xs font-bold text-primary opacity-0 group-hover:opacity-100 transition-opacity">
            Explore
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </span>
    </div>
</a>
