<x-admin-layout>
    <x-slot name="header">Banner Management</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-lg font-bold text-white">Hero Banners</h2>
            <a href="{{ route('admin.banners.create') }}" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium w-full sm:w-auto text-center">Create Banner</a>
        </div>
        
        <div class="overflow-x-auto">
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
        
        <div class="px-6 py-4 border-t border-border">
            {{ $banners->links() }}
        </div>
    </div>
</x-admin-layout>
