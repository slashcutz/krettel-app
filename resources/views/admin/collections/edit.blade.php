<x-admin-layout>
    <x-slot name="header">Edit Collection: {{ $collection->name }}</x-slot>

    <div class="max-w-4xl bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-lg font-bold text-white">Collection Details</h2>
        </div>

        <form action="{{ route('admin.collections.update', $collection->slug) }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-white mb-2">Collection Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $collection->name) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" @input="document.getElementById('slug').value = $event.target.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'')" required>
                        @error('name') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="slug" class="block text-sm font-medium text-white mb-2">Slug</label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $collection->slug) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                        <p class="text-xs text-muted mt-1.5">Auto-generated from the name. You can edit it.</p>
                        @error('slug') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-white mb-2">Description</label>
                    <textarea name="description" id="description" rows="3" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">{{ old('description', $collection->description) }}</textarea>
                </div>

                <div>
                    <label for="image" class="block text-sm font-medium text-white mb-2">Cover Image</label>
                    @if($collection->image)
                        <div class="mb-3">
                            <img src="{{ asset('storage/' . $collection->image) }}" class="h-32 w-48 object-cover rounded-lg border border-border" alt="Current cover">
                        </div>
                    @endif
                    <input type="file" name="image" id="image" accept="image/*" class="bg-secondary border border-border text-white text-sm rounded-lg block w-full p-2.5 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/20 file:text-primary file:text-sm file:font-medium">
                    <p class="text-xs text-muted mt-1.5">Optional. Leave empty to keep the current image. JPEG, PNG, GIF or WebP up to 5MB.</p>
                    @error('image') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="terabox_image" class="block text-sm font-medium text-white mb-2">TeraBox Image URL</label>
                    <input type="text" name="terabox_image" id="terabox_image" value="{{ old('terabox_image', $collection->terabox_image) }}" placeholder="https://... or terabox://remote/path" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    <p class="text-xs text-muted mt-1.5">Optional. Overrides the cover image. Paste a TeraBox image link or a <code>terabox://</code> path.</p>
                    @error('terabox_image') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-white mb-2">Owner</label>
                        <select name="user_id" id="user_id" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id', $collection->user_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="visibility" class="block text-sm font-medium text-white mb-2">Visibility</label>
                        <select name="visibility" id="visibility" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            <option value="public" {{ old('visibility', $collection->visibility) == 'public' ? 'selected' : '' }}>Public</option>
                            <option value="private" {{ old('visibility', $collection->visibility) == 'private' ? 'selected' : '' }}>Private</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="video_ids" class="block text-sm font-medium text-white mb-2">Videos</label>
                    <select name="video_ids[]" id="video_ids" multiple size="8" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                        @forelse($videos as $video)
                            <option value="{{ $video->id }}" {{ collect(old('video_ids', $collection->items->pluck('video_id')->all()))->contains($video->id) ? 'selected' : '' }}>
                                {{ $video->title }} @if($video->video_url === 'terabox-remote') (Ready) @elseif($video->video_url === 'failed') (Failed) @else (Processing) @endif
                            </option>
                        @empty
                            <option value="" disabled>No videos available. Upload one first.</option>
                        @endforelse
                    </select>
                    <p class="text-xs text-muted mt-1.5">Hold Ctrl (or Cmd on Mac) to select multiple videos.</p>
                    @error('video_ids') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex justify-end space-x-4">
                <a href="{{ route('admin.collections.index') }}" class="px-4 py-2 border border-border rounded-lg text-white hover:bg-secondary transition-colors">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors font-medium">Save Changes</button>
            </div>
        </form>
    </div>
</x-admin-layout>
