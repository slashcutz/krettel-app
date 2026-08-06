<x-admin-layout>
    <x-slot name="header">Edit Playlist: {{ $playlist->name }}</x-slot>

    <div class="max-w-4xl bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-lg font-bold text-white">Playlist Details</h2>
        </div>

        <form action="{{ route('admin.playlists.update', $playlist->id) }}" method="POST" class="p-6">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-white mb-2">Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $playlist->name) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="slug" class="block text-sm font-medium text-white mb-2">Slug</label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $playlist->slug) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-white mb-2">Description</label>
                    <textarea name="description" id="description" rows="3" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">{{ old('description', $playlist->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-white mb-2">Owner</label>
                        <select name="user_id" id="user_id" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id', $playlist->user_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="visibility" class="block text-sm font-medium text-white mb-2">Visibility</label>
                        <select name="visibility" id="visibility" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            @foreach(['public', 'private'] as $opt)
                                <option value="{{ $opt }}" {{ old('visibility', $playlist->visibility) == $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex justify-end space-x-4">
                <a href="{{ route('admin.playlists.index') }}" class="px-4 py-2 border border-border rounded-lg text-white hover:bg-secondary transition-colors">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors font-medium">Save Changes</button>
            </div>
        </form>
    </div>
</x-admin-layout>