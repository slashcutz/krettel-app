<x-admin-layout>
    <x-slot name="header">Edit Banner: {{ $banner->title }}</x-slot>

    <div class="max-w-4xl bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-border">
            <h2 class="text-lg font-bold text-white">Banner Details</h2>
        </div>

        <form action="{{ route('admin.banners.update', $banner) }}" method="POST" enctype="multipart/form-data" class="p-4 sm:p-6">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="title" class="block text-sm font-medium text-white mb-2">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $banner->title) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="subtitle" class="block text-sm font-medium text-white mb-2">Subtitle</label>
                        <input type="text" name="subtitle" id="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>

                @php
                    $existingImage = $banner->image_url ? \App\Support\TeraBoxImage::url($banner->image_url, 'banner', $banner->id) : '';
                @endphp
                <div x-data="{ logoPreview: '{{ $existingImage }}' }">
                    <label for="image" class="block text-sm font-medium text-white mb-2">Banner Image</label>
                    <div class="flex flex-col sm:flex-row sm:items-start items-center space-y-3 sm:space-y-0 sm:space-x-3">
                        <div class="w-48 h-20 bg-secondary border border-border rounded-lg overflow-hidden flex items-center justify-center flex-shrink-0 relative">
                            <img :src="logoPreview" x-show="logoPreview" class="max-w-full max-h-full object-contain" alt="Image preview">
                            <span x-show="!logoPreview" class="text-xs text-muted">No Image</span>
                        </div>
                        <div class="flex-1 w-full">
                            <label for="image" class="cursor-pointer inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-secondary border border-border rounded-lg hover:border-primary transition-colors">
                                Choose image
                            </label>
                            <input type="file" name="image" id="image" accept="image/*" class="hidden" @change="const f = $event.target.files[0]; if (f) logoPreview = URL.createObjectURL(f)">
                            <p class="text-xs text-muted mt-2">Optional. JPEG, PNG, GIF or WebP up to 5MB. Leave empty to keep current.</p>
                        </div>
                    </div>
                    @error('image') <p class="text-primary text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="link_url" class="block text-sm font-medium text-white mb-2">Link URL</label>
                    <input type="text" name="link_url" id="link_url" value="{{ old('link_url', $banner->link_url) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" placeholder="https://... or /path">
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="video_id" class="block text-sm font-medium text-white mb-2">Linked Video</label>
                        <select name="video_id" id="video_id" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            <option value="">-- None --</option>
                            @foreach($videos as $video)
                                <option value="{{ $video->id }}" {{ old('video_id', $banner->video_id) == $video->id ? 'selected' : '' }}>{{ $video->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-white mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $banner->sort_order) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-white mb-2">Status</label>
                    <select name="status" id="status" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                        <option value="1" {{ old('status', $banner->status) == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('status', $banner->status) == '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex flex-col-reverse sm:flex-row gap-3 sm:justify-end sm:space-x-4">
                <a href="{{ route('admin.banners.index') }}" class="px-4 py-2.5 border border-border rounded-lg text-white hover:bg-secondary transition-colors text-center w-full sm:w-auto">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-red-600 text-white px-4 py-2.5 rounded-lg transition-colors font-medium w-full sm:w-auto">Save Changes</button>
            </div>
        </form>
    </div>
</x-admin-layout>