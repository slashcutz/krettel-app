<x-admin-layout>
    <x-slot name="header">Collection: {{ $collection->name }}</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold text-white">{{ $collection->name }}</h2>
                <p class="text-sm text-muted mt-1">
                    Created by {{ $collection->user->name ?? 'System' }} &middot; {{ $collection->created_at->format('M d, Y') }} &middot;
                    <span class="{{ $collection->visibility == 'public' ? 'text-success' : 'text-warning' }}">{{ ucfirst($collection->visibility) }}</span>
                </p>
                @if($collection->description)
                    <p class="text-sm text-muted mt-2">{{ $collection->description }}</p>
                @endif
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.collections.edit', $collection->slug) }}" class="text-primary hover:underline">Edit</a>
                <a href="{{ route('admin.collections.index') }}" class="text-muted hover:text-white">Back</a>
            </div>
        </div>

        @if($collection->image)
            <div class="px-6 py-4 border-b border-border">
                <img src="{{ asset('storage/' . $collection->image) }}" class="h-40 w-64 object-cover rounded-lg border border-border" alt="{{ $collection->name }} cover">
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Video</th>
                        <th scope="col" class="px-6 py-3">Visibility</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($collection->items as $item)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4 flex items-center space-x-3">
                                <div class="w-16 h-10 bg-secondary rounded overflow-hidden flex-shrink-0">
                                    <img src="{{ $item->video->thumbnail ? asset('storage/' . $item->video->thumbnail) : 'https://via.placeholder.com/160x100?text=No+Thumbnail' }}" class="w-full h-full object-cover">
                                </div>
                                <span class="font-medium text-white">{{ $item->video->title ?? 'Unknown Video' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    @if(optional($item->video)->visibility == 'public') bg-success/20 text-success 
                                    @else bg-secondary text-muted @endif">
                                    {{ ucfirst(optional($item->video)->visibility ?? 'n/a') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-8 text-center text-muted">No videos in this collection.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
