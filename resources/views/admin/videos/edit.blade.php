<x-admin-layout>
    <x-slot name="header">Edit Video: {{ $video->title }}</x-slot>

    <div class="max-w-4xl bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-border">
            <h2 class="text-lg font-bold text-white">Video Details</h2>
        </div>

        <form action="{{ route('admin.videos.update', $video->id) }}" method="POST" enctype="multipart/form-data" class="p-4 sm:p-6">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="title" class="block text-sm font-medium text-white mb-2">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $video->title) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="slug" class="block text-sm font-medium text-white mb-2">Slug</label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $video->slug) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                </div>

                <div>
                    <label for="short_description" class="block text-sm font-medium text-white mb-2">Short Description</label>
                    <textarea name="short_description" id="short_description" rows="2" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">{{ old('short_description', $video->short_description) }}</textarea>
                </div>

                <div>
                    <label for="full_description" class="block text-sm font-medium text-white mb-2">Full Description</label>
                    <textarea name="full_description" id="full_description" rows="4" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">{{ old('full_description', $video->full_description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-white mb-2">Category</label>
                        <select name="category_id" id="category_id" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            <option value="">-- None --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $video->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="release_date" class="block text-sm font-medium text-white mb-2">Release Date</label>
                        <input type="date" name="release_date" id="release_date" value="{{ old('release_date', optional($video->release_date)->format('Y-m-d')) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                    <div>
                        <label for="age_rating" class="block text-sm font-medium text-white mb-2">Age Rating</label>
                        <input type="text" name="age_rating" id="age_rating" value="{{ old('age_rating', $video->age_rating) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" placeholder="e.g. PG-13">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label for="video_type" class="block text-sm font-medium text-white mb-2">Video Type</label>
                        <input type="text" name="video_type" id="video_type" value="{{ old('video_type', $video->video_type) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                    <div>
                        <label for="resolution" class="block text-sm font-medium text-white mb-2">Resolution</label>
                        <input type="text" name="resolution" id="resolution" value="{{ old('resolution', $video->resolution) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" placeholder="e.g. 1080p">
                    </div>
                    <div>
                        <label for="quality" class="block text-sm font-medium text-white mb-2">Quality</label>
                        <input type="text" name="quality" id="quality" value="{{ old('quality', $video->quality) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label for="thumbnail" class="block text-sm font-medium text-white mb-2">Thumbnail URL / File</label>
                        <input type="url" name="thumbnail" id="thumbnail" value="{{ old('thumbnail', $video->thumbnail) }}" placeholder="Paste Image URL" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 mb-2">
                        <input type="file" name="thumbnail_file" id="thumbnail_file" accept="image/*" class="text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-red-600 block w-full cursor-pointer">
                    </div>
                    <div>
                        <label for="poster" class="block text-sm font-medium text-white mb-2">Poster URL / File</label>
                        <input type="url" name="poster" id="poster" value="{{ old('poster', $video->poster) }}" placeholder="Paste Image URL" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 mb-2">
                        <input type="file" name="poster_file" id="poster_file" accept="image/*" class="text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-red-600 block w-full cursor-pointer">
                    </div>
                    <div>
                        <label for="trailer_url" class="block text-sm font-medium text-white mb-2">Trailer URL</label>
                        <input type="url" name="trailer_url" id="trailer_url" value="{{ old('trailer_url', $video->trailer_url) }}" placeholder="Paste Trailer URL" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="terabox_image" class="block text-sm font-medium text-white mb-2">TeraBox Image URL</label>
                        <input type="text" name="terabox_image" id="terabox_image" value="{{ old('terabox_image', $video->terabox_image) }}" placeholder="https://... or terabox://remote/path" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                        <p class="text-xs text-muted mt-1.5">Image hosted on TeraBox. Paste a direct link or a <code>terabox://</code> path.</p>
                    </div>
                    <div>
                        <label for="previews" class="block text-sm font-medium text-white mb-2">Preview Images (random per card)</label>
                        @for($i = 0; $i < 3; $i++)
                            <input type="text" name="previews[]" value="{{ old('previews.'.$i, $video->previews[$i] ?? '') }}" placeholder="Preview {{ $i + 1 }} URL" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 mb-2">
                        @endfor
                        <p class="text-xs text-muted mt-1.5">Optional. Cards show a random one of these on each load.</p>
                    </div>
                </div>

                <div>
                    <label for="visibility" class="block text-sm font-medium text-white mb-2">Visibility</label>
                    <select name="visibility" id="visibility" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                        @foreach(['public', 'private', 'unlisted'] as $opt)
                            <option value="{{ $opt }}" {{ old('visibility', $video->visibility) == $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex flex-col-reverse sm:flex-row gap-3 sm:justify-end sm:space-x-4">
                <a href="{{ route('admin.videos.index') }}" class="px-4 py-2.5 border border-border rounded-lg text-white hover:bg-secondary transition-colors text-center w-full sm:w-auto">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-red-600 text-white px-4 py-2.5 rounded-lg transition-colors font-medium w-full sm:w-auto">Save Changes</button>
            </div>
        </form>
    </div>
</x-admin-layout>