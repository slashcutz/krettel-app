<x-admin-layout>
    <x-slot name="header">Playlist Management</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex justify-between items-center">
            <h2 class="text-lg font-bold text-white">User Playlists</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Playlist Name</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Creator</th>
                        <th scope="col" class="px-6 py-3">Status</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Created</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($playlists as $playlist)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4 font-medium text-white">{{ $playlist->name }}</td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $playlist->user->name ?? 'System' }}</td>
                            <td class="px-6 py-4">
                                @if($playlist->visibility == 'public')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-success/20 text-success">Public</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-warning/20 text-warning">Private</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">{{ $playlist->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.playlists.show', $playlist->id) }}" class="text-primary hover:underline">View Videos</a>
                                <a href="{{ route('admin.playlists.edit', $playlist->id) }}" class="text-primary hover:underline">Edit</a>
                                <form id="playlist-delete-{{ $playlist->id }}" action="{{ route('admin.playlists.destroy', $playlist->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="confirmDelete('playlist-delete-{{ $playlist->id }}', '{{ addslashes($playlist->name) }}')" class="text-muted hover:text-white">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-muted">No playlists found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $playlists->links() }}
        </div>
    </div>
</x-admin-layout>
