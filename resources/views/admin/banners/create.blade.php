<x-admin-layout>
    <x-slot name="header">Create Banner</x-slot>

    <div class="max-w-4xl bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-lg font-bold text-white">Banner Details</h2>
        </div>

        <form action="{{ route('admin.banners.store') }}" method="POST" class="p-6">
            @csrf

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="title" class="block text-sm font-medium text-white mb-2">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="subtitle" class="block text-sm font-medium text-white mb-2">Subtitle</label>
                        <input type="text" name="subtitle" id="subtitle" value="{{ old('subtitle') }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>

                <div>
                    <label for="image_url" class="block text-sm font-medium text-white mb-2">Image URL</label>
                    <input type="url" name="image_url" id="image_url" value="{{ old('image_url') }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" placeholder="https://...">
                </div>

                <div>
                    <label for="link_url" class="block text-sm font-medium text-white mb-2">Link URL</label>
                    <input type="url" name="link_url" id="link_url" value="{{ old('link_url') }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" placeholder="https://...">
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="video_id" class="block text-sm font-medium text-white mb-2">Linked Video</label>
                        <select name="video_id" id="video_id" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            <option value="">-- None --</option>
                            @foreach($videos as $video)
                                <option value="{{ $video->id }}" {{ old('video_id') == $video->id ? 'selected' : '' }}>{{ $video->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-white mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-white mb-2">Status</label>
                    <select name="status" id="status" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                        @foreach(['active', 'inactive', 'draft'] as $opt)
                            <option value="{{ $opt }}" {{ old('status', 'active') == $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex justify-end space-x-4">
                <a href="{{ route('admin.banners.index') }}" class="px-4 py-2 border border-border rounded-lg text-white hover:bg-secondary transition-colors">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors font-medium">Create Banner</button>
            </div>
        </form>
    </div>
</x-admin-layout>