<x-admin-layout>
    <x-slot name="header">Video Management</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-lg font-bold text-white">All Videos</h2>
            <div class="flex items-center space-x-3 w-full sm:w-auto">
                <input type="text" placeholder="Search videos..." class="bg-secondary border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full sm:w-64 p-2.5">
                <a href="{{ route('upload.index') }}" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium whitespace-nowrap">Upload Video</a>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Video Title</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Category</th>
                        <th scope="col" class="px-6 py-3">Visibility</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Views</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($videos as $video)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4 flex items-center space-x-3">
                                <div class="w-16 h-10 bg-secondary rounded overflow-hidden flex-shrink-0">
                                    <img src="{{ $video->thumbnail ?? 'https://via.placeholder.com/160x100?text=No+Thumbnail' }}" class="w-full h-full object-cover">
                                </div>
                                <div class="font-medium text-white line-clamp-1" title="{{ $video->title }}">
                                    {{ Str::limit($video->title, 40) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $video->category->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    @if($video->visibility == 'public') bg-success/20 text-success 
                                    @elseif($video->visibility == 'private') bg-warning/20 text-warning 
                                    @else bg-secondary text-muted @endif">
                                    {{ ucfirst($video->visibility) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ number_format($video->views) }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.videos.edit', $video->id) }}" class="text-primary hover:underline">Edit</a>
                                <form id="video-delete-{{ $video->id }}" action="{{ route('admin.videos.destroy', $video->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="confirmDelete('video-delete-{{ $video->id }}', '{{ addslashes($video->title) }}')" class="text-muted hover:text-white">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $videos->links() }}
        </div>
    </div>
</x-admin-layout>
