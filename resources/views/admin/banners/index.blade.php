<x-admin-layout>
    <x-slot name="header">Banner Management</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-lg font-bold text-white">Hero Banners</h2>
            <a href="{{ route('admin.banners.create') }}" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium w-full sm:w-auto text-center">Create Banner</a>
        </div>
        
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Preview</th>
                        <th scope="col" class="px-6 py-3">Title</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Linked Video</th>
                        <th scope="col" class="px-6 py-3 hidden sm:table-cell">Status</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($banners as $banner)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="w-32 h-16 bg-secondary rounded overflow-hidden">
                                    <img src="{{ $banner->image_url }}" class="w-full h-full object-cover">
                                </div>
                            </td>
                            <td class="px-6 py-4 font-medium text-white">{{ $banner->title }}</td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $banner->video->title ?? 'N/A' }}</td>
                            <td class="px-6 py-4 hidden sm:table-cell">
                                @if($banner->status == 'active')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-success/20 text-success">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-secondary text-muted">{{ ucfirst($banner->status ?? 'Inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.banners.edit', $banner->id) }}" class="text-primary hover:underline">Edit</a>
                                <form id="banner-delete-{{ $banner->id }}" action="{{ route('admin.banners.destroy', $banner->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="confirmDelete('banner-delete-{{ $banner->id }}', '{{ addslashes($banner->title) }}')" class="text-muted hover:text-white">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-muted">No banners found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List -->
        <div class="md:hidden divide-y divide-border">
            @forelse($banners as $banner)
            <div class="p-4">
                <div class="flex items-start gap-3">
                    <div class="w-24 h-14 bg-secondary rounded overflow-hidden flex-shrink-0">
                        <img src="{{ $banner->image_url }}" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white line-clamp-2">{{ $banner->title }}</p>
                        <p class="text-xs text-muted mt-1">{{ $banner->video->title ?? 'No linked video' }}</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    @if($banner->status == 'active')
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-success/20 text-success">Active</span>
                    @else
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-secondary text-muted">{{ ucfirst($banner->status ?? 'Inactive') }}</span>
                    @endif
                    <div class="flex items-center gap-6">
                        <a href="{{ route('admin.banners.edit', $banner->id) }}" class="text-sm font-medium text-primary hover:underline">Edit</a>
                        <form id="banner-delete-{{ $banner->id }}" action="{{ route('admin.banners.destroy', $banner->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="button" onclick="confirmDelete('banner-delete-{{ $banner->id }}', '{{ addslashes($banner->title) }}')" class="text-sm text-muted hover:text-white">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">No banners found.</p>
            @endforelse
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $banners->links() }}
        </div>
    </div>
</x-admin-layout>
