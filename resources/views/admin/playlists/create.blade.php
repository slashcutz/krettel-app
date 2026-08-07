<x-admin-layout>
    <x-slot name="header">Create Playlist</x-slot>

    <div class="w-full" x-data="{ search: '', selected: new Set({{ json_encode(old('video_ids', [])) }}) }">
        <form action="{{ route('admin.playlists.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">

                <!-- Playlist Details -->
                <div class="lg:col-span-1 bg-card border border-border rounded-xl overflow-hidden h-fit">
                    <div class="px-5 md:px-6 py-3.5 md:py-4 border-b border-border flex items-center space-x-3">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                        <h2 class="text-base md:text-lg font-bold text-white">Playlist Details</h2>
                    </div>

                    <div class="p-5 md:p-6 space-y-5">
                        <div>
                            <label for="name" class="block text-sm font-medium text-muted mb-1.5">Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary" @input="document.getElementById('slug').value = $event.target.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'')">
                            @error('name') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="slug" class="block text-sm font-medium text-muted mb-1.5">Slug</label>
                            <input type="text" name="slug" id="slug" value="{{ old('slug') }}" required class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary font-mono text-sm">
                            <p class="text-xs text-muted mt-1.5">Auto-generated from the name. You can edit it.</p>
                            @error('slug') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-muted mb-1.5">Description</label>
                            <textarea name="description" id="description" rows="3" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">{{ old('description') }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="user_id" class="block text-sm font-medium text-muted mb-1.5">Owner</label>
                                <select name="user_id" id="user_id" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('user_id', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="visibility" class="block text-sm font-medium text-muted mb-1.5">Visibility</label>
                                <select name="visibility" id="visibility" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">
                                    <option value="public" {{ old('visibility') == 'public' ? 'selected' : '' }}>Public</option>
                                    <option value="private" {{ old('visibility') == 'private' || old('visibility') === null ? 'selected' : '' }}>Private</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 md:px-6 py-4 border-t border-border bg-secondary/20 flex flex-col sm:flex-row justify-end gap-3">
                        <a href="{{ route('admin.playlists.index') }}" class="w-full sm:w-auto px-4 py-2 border border-border rounded-lg text-white hover:bg-secondary transition-colors text-center">Cancel</a>
                        <button type="submit" class="w-full sm:w-auto bg-primary hover:bg-red-600 text-white px-6 py-2 rounded-lg transition-colors font-bold">Create Playlist</button>
                    </div>
                </div>

                <!-- Video Selection -->
                <div class="lg:col-span-2 bg-card border border-border rounded-xl overflow-hidden">
                    <div class="px-5 md:px-6 py-3.5 md:py-4 border-b border-border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path></svg>
                            <h2 class="text-base md:text-lg font-bold text-white">Videos</h2>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-primary/10 text-primary" x-text="selected.size + ' selected'"></span>
                        </div>
                        <div class="w-full sm:w-64 relative">
                            <input type="text" x-model="search" placeholder="Search videos..." class="w-full bg-secondary border border-border text-white rounded-lg pl-9 pr-3 py-2 text-sm focus:ring-primary focus:border-primary">
                            <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>

                    <div class="p-5 md:p-6">
                        @forelse($videos as $video)
                            <div class="flex items-center py-2.5 px-3 rounded-lg hover:bg-secondary/40 transition-colors" x-show="!search || '{{ strtolower($video->title) }}'.includes(search.toLowerCase())">
                                <input type="checkbox" name="video_ids[]" value="{{ $video->id }}" id="video_{{ $video->id }}" class="rounded border-border text-primary focus:ring-primary bg-secondary mr-4" :checked="selected.has('{{ $video->id }}')" @change="selected.has('{{ $video->id }}') ? selected.delete('{{ $video->id }}') : selected.add('{{ $video->id }}')">
                                <label for="video_{{ $video->id }}" class="flex items-center flex-1 min-w-0 cursor-pointer">
                                    <div class="w-20 h-12 bg-secondary border border-border rounded overflow-hidden flex-shrink-0 mr-4">
                                        <img src="{{ $video->thumbnail ? \App\Support\TeraBoxImage::url($video->thumbnail ?: $video->poster ?: $video->terabox_image, 'video', $video->id) : 'https://via.placeholder.com/160x100?text=No+Thumbnail' }}" class="w-full h-full object-cover" alt="">
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-white truncate">{{ $video->title }}</p>
                                        <p class="text-xs text-muted">{{ optional($video->category)->name ?? 'Uncategorized' }} &middot; {{ $video->resolution ?? 'N/A' }}</p>
                                    </div>
                                </label>
                                <span class="text-xs px-2 py-1 rounded-full flex-shrink-0 ml-2 {{ $video->video_url === 'terabox-remote' ? 'bg-success/10 text-success' : ($video->video_url === 'failed' ? 'bg-warning/10 text-warning' : 'bg-secondary text-muted') }}">
                                    {{ $video->video_url === 'terabox-remote' ? 'Ready' : ($video->video_url === 'failed' ? 'Failed' : 'Processing') }}
                                </span>
                            </div>
                        @empty
                            <p class="text-center text-muted py-10">No videos available yet. <a href="{{ route('upload.index') }}" class="text-primary hover:underline">Upload one</a>.</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </form>
    </div>
</x-admin-layout>
