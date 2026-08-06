@props(['collection' => null])

@php
    $image = data_get($collection, 'terabox_image') ?: data_get($collection, 'image');
    $collectionId = data_get($collection, 'id');
    $imageUrl = $image ? \App\Support\TeraBoxImage::url($image, 'collection', $collectionId) : null;

    $imageUrl = $imageUrl ?: 'https://via.placeholder.com/200x200?text=' . urlencode(data_get($collection, 'name', 'Collection'));
@endphp

<a href="{{ route('collection.show', data_get($collection, 'slug', data_get($collection, 'id', 'sample-collection'))) }}"
   class="flex flex-col items-center group w-24 sm:w-28 flex-shrink-0 cursor-pointer">
    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full overflow-hidden border-2 border-border group-hover:border-primary transition-all duration-300 transform group-hover:scale-105 group-hover:shadow-2xl bg-secondary">
        <img src="{{ $imageUrl }}" alt="{{ data_get($collection, 'name', 'Collection') }}" loading="eager" fetchpriority="high" decoding="async" class="w-full h-full object-cover">
    </div>
    <span class="mt-2 text-xs sm:text-sm font-medium text-muted group-hover:text-white text-center truncate w-full">
        {{ data_get($collection, 'name', 'Collection') }}
    </span>
</a>
