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

                </div>

                <!-- Video File Source / Mapping -->
                <div class="p-4 bg-zinc-950/40 border border-zinc-800/80 rounded-xl space-y-4">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide">Video File / Path</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="video_file" class="block text-xs font-medium text-gray-400 mb-1.5">Replace Local Video File (Optional)</label>
                            <input type="file" name="video_file" id="video_file" accept="video/*" class="text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-primary file:text-white hover:file:bg-red-600 block w-full cursor-pointer">
                        </div>
                        <div>
                            <label for="storage_folder" class="block text-xs font-medium text-gray-400 mb-1.5">TeraBox Remote File Path (Optional)</label>
                            <input type="text" name="storage_folder" id="storage_folder" value="{{ old('storage_folder', $video->storage_folder) }}" placeholder="e.g. /Apps/Krettel/my-file.mp4" class="bg-secondary border border-border text-white text-xs rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            <p class="text-[9px] text-muted mt-1">If the video is already uploaded directly to TeraBox, paste the path here (e.g. <code>/Apps/Krettel/filename.mp4</code>).</p>
                        </div>
                    </div>
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

                <!-- Media URLs & Files with Live Previews -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6" 
                     x-data="{
                         thumbPreview: '{{ $video->thumbnail }}',
                         posterPreview: '{{ $video->poster }}',
                         triggerClick(id) { document.getElementById(id).click() },
                         onFileChange(e, key) {
                             const file = e.target.files[0];
                             if (file) {
                                 this[key] = URL.createObjectURL(file);
                             }
                         }
                     }">
                    
                    <!-- Thumbnail File & URL -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-white">Thumbnail</label>
                        <div @click="triggerClick('thumbnail_file')" class="relative border-2 border-dashed border-border rounded-xl aspect-video overflow-hidden bg-secondary/50 flex flex-col items-center justify-center text-center cursor-pointer hover:border-primary transition group">
                            <template x-if="thumbPreview">
                                <img :src="thumbPreview" class="absolute inset-0 w-full h-full object-cover">
                            </template>
                            <div class="relative z-10 p-4 bg-black/60 rounded-xl m-2 text-center group-hover:bg-primary/90 transition-colors">
                                <p class="text-xs font-bold text-white">Choose / Upload Image</p>
                                <p class="text-[10px] text-gray-300 mt-0.5">Click to browse file</p>
                            </div>
                            <input type="file" name="thumbnail_file" id="thumbnail_file" accept="image/*" @change="onFileChange($event, 'thumbPreview')" class="hidden">
                        </div>
                        <input type="url" name="thumbnail" x-model="thumbPreview" placeholder="Or paste image URL" class="bg-secondary border border-border text-white text-xs rounded-lg focus:ring-primary focus:border-primary block w-full p-2">
                    </div>

                    <!-- Poster File & URL -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-white">Poster</label>
                        <div @click="triggerClick('poster_file')" class="relative border-2 border-dashed border-border rounded-xl aspect-[2/3] overflow-hidden bg-secondary/50 flex flex-col items-center justify-center text-center cursor-pointer hover:border-primary transition group" style="max-height: 240px;">
                            <template x-if="posterPreview">
                                <img :src="posterPreview" class="absolute inset-0 w-full h-full object-cover">
                            </template>
                            <div class="relative z-10 p-4 bg-black/60 rounded-xl m-2 text-center group-hover:bg-primary/90 transition-colors">
                                <p class="text-xs font-bold text-white">Choose / Upload Image</p>
                                <p class="text-[10px] text-gray-300 mt-0.5">Click to browse file</p>
                            </div>
                            <input type="file" name="poster_file" id="poster_file" accept="image/*" @change="onFileChange($event, 'posterPreview')" class="hidden">
                        </div>
                        <input type="url" name="poster" x-model="posterPreview" placeholder="Or paste image URL" class="bg-secondary border border-border text-white text-xs rounded-lg focus:ring-primary focus:border-primary block w-full p-2">
                    </div>

                    <!-- Trailer & TeraBox Image URL -->
                    <div class="space-y-4 flex flex-col justify-between">
                        <div>
                            <label for="trailer_url" class="block text-sm font-medium text-white mb-2">Trailer URL</label>
                            <input type="url" name="trailer_url" id="trailer_url" value="{{ old('trailer_url', $video->trailer_url) }}" placeholder="Paste Trailer URL" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                        </div>
                        <div>
                            <label for="terabox_image" class="block text-sm font-medium text-white mb-2">TeraBox Image URL</label>
                            <input type="text" name="terabox_image" id="terabox_image" value="{{ old('terabox_image', $video->terabox_image) }}" placeholder="https://... or terabox://remote/path" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                            <p class="text-[10px] text-muted mt-1.5">Image hosted on TeraBox. Paste direct link or <code>terabox://</code> path.</p>
                        </div>
                    </div>
                </div>

                <!-- Previews with Live Individual Previews -->
                <div class="border-t border-border pt-6"
                     x-data="{
                         previews: [
                             '{{ $video->previews[0] ?? '' }}',
                             '{{ $video->previews[1] ?? '' }}',
                             '{{ $video->previews[2] ?? '' }}'
                         ],
                         triggerClick(idx) { document.getElementById('preview_file_' + idx).click() },
                         onPreviewChange(e, idx) {
                             const file = e.target.files[0];
                             if (file) {
                                 this.previews[idx] = URL.createObjectURL(file);
                             }
                         }
                     }">
                    <label class="block text-sm font-medium text-white mb-3">Preview Images (random per card)</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @for($i = 0; $i < 3; $i++)
                            <div class="space-y-2 p-3 bg-zinc-950/40 rounded-xl border border-zinc-800/80">
                                <span class="text-[11px] text-gray-500 font-bold block">Preview #{{ $i + 1 }}</span>
                                <div @click="triggerClick({{ $i }})" class="relative border border-dashed border-border rounded-lg aspect-video overflow-hidden bg-secondary/30 flex items-center justify-center text-center cursor-pointer hover:border-primary transition group">
                                    <template x-if="previews[{{ $i }}]">
                                        <img :src="previews[{{ $i }}]" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    <div class="relative z-10 p-2 bg-black/60 rounded-md m-1 text-center group-hover:bg-primary/90 transition-colors">
                                        <span class="text-[10px] font-bold text-white">Upload</span>
                                    </div>
                                    <input type="file" name="preview_files[]" id="preview_file_{{ $i }}" accept="image/*" @change="onPreviewChange($event, {{ $i }})" class="hidden">
                                </div>
                                <input type="text" name="previews[]" x-model="previews[{{ $i }}]" placeholder="Or paste preview URL" class="bg-secondary border border-border text-white text-[11px] rounded-lg focus:ring-primary focus:border-primary block w-full p-2">
                            </div>
                        @endfor
                    </div>
                    <p class="text-xs text-muted mt-2">Optional. Cards show a random one of these on each load.</p>
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