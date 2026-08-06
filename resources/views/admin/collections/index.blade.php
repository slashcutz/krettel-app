<x-admin-layout>
    <x-slot name="header">Collection Management</x-slot>

    <div class="flex justify-end mb-4">
        <a href="{{ route('admin.collections.create') }}" class="inline-flex items-center bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors font-medium">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create Collection
        </a>
    </div>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex justify-between items-center">
            <h2 class="text-lg font-bold text-white">Collections</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Collection</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Creator</th>
                        <th scope="col" class="px-6 py-3 hidden sm:table-cell">Videos</th>
                        <th scope="col" class="px-6 py-3">Status</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Created</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($collections as $collection)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    @if($collection->image)
                                        <div class="w-16 h-10 bg-secondary rounded overflow-hidden flex-shrink-0">
                                            <img src="{{ asset('storage/' . $collection->image) }}" class="w-full h-full object-cover" alt="">
                                        </div>
                                    @endif
                                    <span class="font-medium text-white">{{ $collection->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $collection->user->name ?? 'System' }}</td>
                            <td class="px-6 py-4 hidden sm:table-cell">{{ $collection->items_count }}</td>
                            <td class="px-6 py-4">
                                @if($collection->visibility == 'public')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-success/20 text-success">Public</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-warning/20 text-warning">Private</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">{{ $collection->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.collections.show', $collection->slug) }}" class="text-primary hover:underline">View</a>
                                <a href="{{ route('admin.collections.edit', $collection->slug) }}" class="text-primary hover:underline">Edit</a>
                                <form id="collection-delete-{{ $collection->id }}" action="{{ route('admin.collections.destroy', $collection->slug) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="confirmDelete('collection-delete-{{ $collection->id }}', '{{ addslashes($collection->name) }}')" class="text-muted hover:text-white">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-muted">No collections found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $collections->links() }}
        </div>
    </div>
</x-admin-layout>
