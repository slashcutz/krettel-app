<x-admin-layout>
    <x-slot name="header">Upload Video</x-slot>
    <div class="py-2" x-data="videoUploadWizard()">
        <div class="max-w-7xl 2xl:max-w-[1600px] mx-auto sm:px-6 lg:px-8 2xl:px-12">
            <div class="bg-card overflow-hidden shadow-sm sm:rounded-lg border border-border">
                
                <!-- Wizard Header / Progress -->
                <div class="border-b border-border bg-secondary/50 p-4 sm:p-6 flex justify-between items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-bold text-white">Upload Video</h2>
                    <div class="flex items-center space-x-2 text-sm flex-shrink-0">
                        <span class="text-muted">Step</span>
                        <span class="text-white font-bold px-2 py-1 bg-primary rounded-md" x-text="step"></span>
                        <span class="text-muted">of 7</span>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row min-h-[600px]">
                    <!-- Sidebar Navigation (horizontal stepper on mobile) -->
                    <div class="flex md:flex-col overflow-x-auto md:overflow-visible md:w-64 border-b md:border-b-0 md:border-r border-border bg-secondary/20 p-3 md:p-4 gap-2 md:space-y-2">
                        <template x-for="(s, index) in steps" :key="index">
                            <button 
                                @click="step = index + 1"
                                class="flex-1 md:flex-none whitespace-nowrap md:whitespace-normal text-left px-4 py-3 rounded-lg transition-colors flex items-center justify-between"
                                :class="step === (index + 1) ? 'bg-primary text-white font-medium shadow-md' : 'text-muted hover:bg-secondary hover:text-white'"
                            >
                                <span x-text="s"></span>
                                <svg x-show="step > (index + 1)" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </button>
                        </template>
                    </div>

                    <!-- Main Form Area -->
                    <div class="flex-1 p-4 sm:p-6 md:p-10 pb-32 lg:pb-10 relative">
                        <form action="{{ route('upload.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                            @csrf
                            
                             <!-- Step 1: Media Upload -->
                             <div x-show="step === 1" x-transition.opacity.duration.300ms x-data="{ mediaSource: 'upload' }">
                                 <h3 class="text-xl font-bold text-white mb-4">Media Upload</h3>
                                 
                                 <!-- Source selector tabs -->
                                 <div class="flex bg-secondary/40 rounded-xl p-1 mb-6 max-w-md border border-white/5" x-data="{ init() { mediaSource = 'direct' } }">
                                     <button type="button" @click="mediaSource = 'direct'" class="flex-1 text-center py-2 rounded-lg text-xs font-bold transition-all" :class="mediaSource === 'direct' ? 'bg-primary text-white shadow-lg' : 'text-gray-400 hover:text-white'">Direct Upload</button>
                                     <button type="button" @click="mediaSource = 'manual'" class="flex-1 text-center py-2 rounded-lg text-xs font-bold transition-all" :class="mediaSource === 'manual' ? 'bg-primary text-white shadow-lg' : 'text-gray-400 hover:text-white'">Manual Upload</button>
                                 </div>

                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                     <!-- Video Selection Slot -->
                                     <div>
                                         <template x-if="mediaSource === 'direct'">
                                             <div @click="triggerFile('video_file_input')" class="border-2 border-dashed border-border rounded-xl p-6 flex flex-col items-center justify-center text-center hover:border-primary transition-colors cursor-pointer group min-h-[180px]">
                                                 <svg class="w-10 h-10 text-muted group-hover:text-primary mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                                 <template x-if="!videoPreview">
                                                     <div class="text-center">
                                                         <p class="text-white font-medium">Upload Video File</p>
                                                         <p class="text-xs text-muted mt-1">MP4, MKV up to 4GB</p>
                                                     </div>
                                                 </template>
                                                 <template x-if="videoPreview">
                                                     <video :src="videoPreview" class="w-full max-h-48 rounded-lg object-contain bg-black" controls></video>
                                                 </template>
                                                 <p x-show="videoFile" class="text-xs text-success font-medium mt-2" x-text="videoFile ? 'Selected: ' + videoFile.name : ''"></p>
                                                 <input type="file" id="video_file_input" name="video_file" accept="video/*" @change="onVideoSelect($event)" class="hidden">
                                             </div>
                                         </template>

                                         <template x-if="mediaSource === 'manual'">
                                             <div class="bg-secondary/20 border border-border rounded-xl p-6 min-h-[180px] flex flex-col justify-center">
                                                 <label for="terabox_file_path" class="block text-sm font-medium text-white mb-2">TeraBox File Name or Path</label>
                                                 <input type="text" name="terabox_file_path" id="terabox_file_path" placeholder="e.g. Captain_America.mp4" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                                                 <p class="text-[10px] text-muted mt-2">Upload the file directly to your TeraBox account inside <code>/Apps/Krettel/</code> first, then paste the file name here to make linking instant!</p>
                                             </div>
                                         </template>
                                     </div>

                                     <!-- Thumbnail Upload Slot -->
                                     <div @click="triggerFile('thumbnail_file_input')" class="border-2 border-dashed border-border rounded-xl p-6 flex flex-col items-center justify-center text-center hover:border-primary transition-colors cursor-pointer group min-h-[180px]">
                                         <svg class="w-10 h-10 text-muted group-hover:text-primary mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                         <template x-if="!thumbnailFile">
                                             <div class="text-center">
                                                 <p class="text-white font-medium">Upload Thumbnail</p>
                                                 <p class="text-xs text-muted mt-1">JPG, PNG (16:9)</p>
                                             </div>
                                         </template>
                                         <template x-if="thumbnailFile">
                                             <img :src="thumbnailPreview" class="w-full max-h-48 rounded-lg object-cover">
                                         </template>
                                         <p x-show="thumbnailFile" x-text="thumbnailFile ? 'Selected: ' + thumbnailFile.name : ''" class="text-xs text-gray-400 font-medium mt-2"></p>
                                         <input type="file" id="thumbnail_file_input" name="thumbnail" accept="image/*" @change="onThumbnailSelect($event)" class="hidden">
                                     </div>
                                 </div>
                             </div>

                            <!-- Step 2: Basic Details -->
                            <div x-show="step === 2" x-transition.opacity.duration.300ms style="display: none;">
                                <h3 class="text-xl font-bold text-white mb-6">Basic Details</h3>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 gap-4">
                                        <div>
                                            <x-input-label for="terabox_image" value="Image URL (optional)" />
                                            <x-text-input id="terabox_image" class="block mt-1 w-full" type="text" name="terabox_image" placeholder="https://... or pixeldrain://file-id" />
                                        </div>
                                        <div>
                                            <x-input-label for="previews" value="Preview Images (optional, shown randomly on cards)" />
                                            <div class="space-y-4 mt-2">
                                                @for($i = 0; $i < 3; $i++)
                                                    <div class="p-3 bg-zinc-950/40 rounded-xl border border-zinc-800/80">
                                                        <span class="text-[10px] text-gray-500 font-bold mb-1.5 block">Preview #{{ $i + 1 }}</span>
                                                        <input type="text" name="previews[]" class="block w-full rounded-md border-border bg-secondary text-white focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-xs mb-2 p-2" placeholder="Preview {{ $i + 1 }} URL">
                                                        <input type="file" name="preview_files[]" accept="image/*" class="text-xs text-gray-400 file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-primary file:text-white hover:file:bg-red-600 block w-full cursor-pointer">
                                                    </div>
                                                @endfor
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label for="title" value="Title" />
                                        <x-text-input id="title" x-ref="title" class="block mt-1 w-full" type="text" name="title" required />
                                        @if($errors->has('title'))
                                            <p class="text-primary text-xs mt-1">{{ $errors->first('title') }}</p>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="category" value="Category" />
                                            <select name="category_id" class="mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                                                @forelse($categories as $cat)
                                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                @empty
                                                    <option value="" disabled>No categories available</option>
                                                @endforelse
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="age_rating" value="Age Rating" />
                                            <select name="age_rating" class="mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                                                <option value="G">G</option>
                                                <option value="PG">PG</option>
                                                <option value="PG-13">PG-13</option>
                                                <option value="R">R</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label for="description" value="Full Description" />
                                        <textarea id="description" name="full_description" rows="5" class="mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary focus:ring-primary"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Streaming -->
                            <div x-show="step === 3" x-transition.opacity.duration.300ms style="display: none;">
                                <h3 class="text-xl font-bold text-white mb-6">Streaming Specs</h3>
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="resolution" value="Resolution" />
                                        <select name="resolution" class="mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary">
                                            <option value="4K">4K</option>
                                            <option value="1080p">1080p</option>
                                            <option value="720p">720p</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="storage" value="Storage Provider" />
                                        <select name="storage_provider" class="mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary">
                                            <option value="pixeldrain" selected>Pixeldrain (Cloud)</option>
                                            <option value="terabox">TeraBox (Cloud)</option>
                                            <option value="local">Local Storage</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Multiple Audio -->
                            <div x-show="step === 4" x-transition.opacity.duration.300ms style="display: none;">
                                <h3 class="text-xl font-bold text-white mb-6">Audio Tracks</h3>
                                <p class="text-xs text-muted mb-4">Optional. Add extra audio files to mux into the video — the player will show an audio switcher. The first track (or the one marked default) plays by default.</p>
                                <div id="audio-tracks-container" class="space-y-4"></div>
                                <button type="button" onclick="addAudioTrack()" class="text-primary text-sm font-medium hover:underline">+ Add another audio track</button>

                                <template id="audio-track-template">
                                    <div class="audio-track-row border border-border p-4 rounded-lg bg-secondary/30">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label value="Audio Language" />
                                                <select name="audio_language[0]" class="audio-language mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary">
                                                    @foreach(\App\Support\LanguageCodes::names() as $lang)
                                                        <option value="{{ $lang }}" @if($loop->first) selected @endif>{{ $lang }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Upload Audio File" />
                                                <input type="file" name="audio_files[0]" class="audio-file mt-1 block w-full text-white" accept="audio/*">
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center justify-between">
                                            <div class="flex items-center space-x-2">
                                                <input type="checkbox" name="default_audio[0]" class="audio-default rounded border-border text-primary focus:ring-primary bg-secondary">
                                                <span class="text-white text-sm">Set as Default Audio</span>
                                            </div>
                                            <button type="button" onclick="removeAudioTrack(this)" class="text-red-400 text-sm hover:text-red-300">Remove</button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Step 5: Subtitles -->
                            <div x-show="step === 5" x-transition.opacity.duration.300ms style="display: none;">
                                <h3 class="text-xl font-bold text-white mb-6">Subtitles</h3>
                                <div class="space-y-4">
                                    <div class="border border-border p-4 rounded-lg bg-secondary/30">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label value="Subtitle Language" />
                                                <select name="subtitle_language[]" class="mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary">
                                                    <option>English (CC)</option>
                                                    <option>Spanish</option>
                                                    <option>French</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Upload Subtitle (.vtt, .srt)" />
                                                <input type="file" name="subtitle_files[]" class="mt-1 block w-full text-white">
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center space-x-2">
                                            <input type="checkbox" name="default_subtitle[]" class="rounded border-border text-primary focus:ring-primary bg-secondary">
                                            <span class="text-white text-sm">Set as Default Subtitle</span>
                                        </div>
                                    </div>
                                    <button type="button" class="text-primary text-sm font-medium hover:underline">+ Add another subtitle</button>
                                </div>
                            </div>

                            <!-- Step 6: Visibility -->
                            <div x-show="step === 6" x-transition.opacity.duration.300ms style="display: none;">
                                <h3 class="text-xl font-bold text-white mb-6">Visibility</h3>
                                <div class="space-y-4">
                                    <div class="flex items-center space-x-3 p-4 border border-border rounded-lg hover:border-primary cursor-pointer">
                                        <input type="radio" name="visibility" value="public" class="text-primary focus:ring-primary bg-secondary border-border" checked>
                                        <div>
                                            <h4 class="text-white font-medium">Public</h4>
                                            <p class="text-xs text-muted">Everyone can see this video.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3 p-4 border border-border rounded-lg hover:border-primary cursor-pointer">
                                        <input type="radio" name="visibility" value="private" class="text-primary focus:ring-primary bg-secondary border-border">
                                        <div>
                                            <h4 class="text-white font-medium">Private</h4>
                                            <p class="text-xs text-muted">Only you and people you choose can watch.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3 p-4 border border-border rounded-lg hover:border-primary cursor-pointer">
                                        <input type="radio" name="visibility" value="scheduled" class="text-primary focus:ring-primary bg-secondary border-border">
                                        <div>
                                            <h4 class="text-white font-medium">Scheduled</h4>
                                            <p class="text-xs text-muted">Select a date to make this video public.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 7: SEO -->
                            <div x-show="step === 7" x-transition.opacity.duration.300ms style="display: none;">
                                <h3 class="text-xl font-bold text-white mb-6">SEO & Metadata</h3>
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label value="SEO Title" />
                                        <x-text-input class="block mt-1 w-full" type="text" name="seo_title" placeholder="Optimized title for search engines" />
                                    </div>
                                    <div>
                                        <x-input-label value="Meta Description" />
                                        <textarea name="meta_description" rows="3" class="mt-1 block w-full rounded-md border-border bg-secondary text-white focus:border-primary focus:ring-primary" placeholder="Brief summary of the video for search results"></textarea>
                                    </div>
                                    <div>
                                        <x-input-label value="Keywords" />
                                        <x-text-input class="block mt-1 w-full" type="text" name="keywords" placeholder="action, sci-fi, space (comma separated)" />
                                    </div>
                                </div>
                            </div>

                            <!-- Premium Mobile Upload Overlay -->
                            <div x-show="mobileUploading" style="display: none;" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/95 backdrop-blur-2xl p-6">
                                <div class="w-full max-w-sm flex flex-col items-center text-center">
                                    <!-- Animated Circular Progress -->
                                    <div class="relative w-32 h-32 mb-8">
                                        <svg class="absolute inset-0 w-full h-full text-white/10" fill="none" viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="6"></circle>
                                        </svg>
                                        <svg class="absolute inset-0 w-full h-full text-primary drop-shadow-[0_0_15px_rgba(229,9,20,0.8)]" fill="none" viewBox="0 0 100 100" style="transform: rotate(-90deg);">
                                            <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="6" stroke-dasharray="283" :stroke-dashoffset="283 - (283 * mobileProgress / 100)" stroke-linecap="round" class="transition-all duration-300 ease-out"></circle>
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-3xl font-bold text-white" x-text="mobileProgress + '%'"></span>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-2xl font-bold text-white mb-2">Uploading Video</h3>
                                    <p class="text-sm text-gray-400 mb-8" x-text="mobileStatus"></p>
                                    
                                    <div class="w-full bg-secondary/50 rounded-2xl p-5 border border-white/5 space-y-4 shadow-xl">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-400 text-xs uppercase tracking-wider font-semibold">Speed</span>
                                            <span class="text-white font-bold text-sm" x-text="mobileSpeed"></span>
                                        </div>
                                        <div class="w-full h-px bg-white/10"></div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-400 text-xs uppercase tracking-wider font-semibold">Time Left</span>
                                            <span class="text-white font-bold text-sm" x-text="mobileEta"></span>
                                        </div>
                                    </div>
                                    
                                    <p class="mt-8 text-xs text-gray-500 max-w-[250px] mx-auto">Please keep the app open and do not lock your screen until the upload finishes.</p>

                                    <template x-if="askResume">
                                        <div class="mt-5 rounded-xl border border-amber-800 bg-amber-950/30 p-4 w-full">
                                            <p class="text-sm text-amber-200 font-medium mb-1">Previous upload found</p>
                                            <p class="text-xs text-gray-400 mb-4">This file was already uploaded to the server before. Reuse it (instant) or upload from scratch?</p>
                                            <div class="flex gap-2">
                                                <button @click="askResumeSubmit()" class="flex-1 bg-primary text-white text-sm rounded-lg px-3 py-2">Reuse existing</button>
                                                <button @click="askResumeStartFresh()" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg px-3 py-2">Upload from scratch</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Action Buttons (fixed bottom bar on mobile/tablet, static on desktop) -->
                            <div x-show="!mobileUploading" class="fixed bottom-24 inset-x-0 z-30 border-t border-border bg-card/95 backdrop-blur-md px-4 py-3 flex flex-col-reverse sm:flex-row gap-3 sm:gap-4 sm:justify-between lg:static lg:mt-8 lg:border-t lg:bg-transparent lg:backdrop-blur-none lg:px-0 lg:py-0 lg:pt-4">
                                <button type="button" @click="step--" x-show="step > 1" class="px-6 py-3 rounded-lg border border-border text-white hover:bg-secondary transition-colors w-full sm:w-auto">
                                    Back
                                </button>
                                <div x-show="step === 1" class="hidden md:block"></div>
                                
                                <button type="button" @click="step++" x-show="step < 7" class="px-6 py-3 rounded-lg bg-white text-black font-bold hover:bg-gray-200 transition-colors w-full sm:w-auto">
                                    Next Step
                                </button>
                                
                                <button type="button" @click.prevent="submitForm()" x-show="step === 7" class="px-6 py-3 rounded-lg bg-primary text-white font-bold hover:bg-red-600 transition-colors shadow-[0_0_15px_rgba(239,68,68,0.5)] w-full sm:w-auto">
                                    Publish Video
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const PROGRESS_URL = '{{ route("upload.progress", "__TOKEN__") }}';

        function videoUploadWizard() {
            return {
                step: 1,
                steps: [
                    'Media Upload',
                    'Basic Details',
                    'Streaming Specs',
                    'Audio Tracks',
                    'Subtitles',
                    'Visibility',
                    'SEO & Meta'
                ],
                videoFile: null,
                videoPreview: null,
                thumbnailFile: null,
                thumbnailPreview: null,
                mobileUploading: false,
                mobileProgress: 0,
                mobileStatus: '',
                mobileSpeed: '--',
                mobileEta: 'calculating...',
                mobileLastLoaded: 0,
                mobileLastTime: 0,
                askResume: false,
                askResumeSubmit: null,
                askResumeStartFresh: null,
                triggerFile(id) {
                    document.getElementById(id).click();
                },
                onVideoSelect(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const MAX_SIZE = 4 * 1024 * 1024 * 1024; // 4GB
                    if (file.size > MAX_SIZE) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'File too large',
                            text: 'Maximum upload size is 4GB. Please select a smaller file.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#e50914',
                            background: '#1a1a1a',
                            color: '#fff',
                        });
                        e.target.value = '';
                        return;
                    }
                    this.videoFile = file;
                    if (file.type.startsWith('video/')) {
                        this.videoPreview = URL.createObjectURL(file);
                    }
                    this.autoFillTitle(e);
                },
                onThumbnailSelect(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    this.thumbnailFile = file;
                    this.thumbnailPreview = URL.createObjectURL(file);
                },
                autoFillTitle(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const titleInput = this.$refs.title;
                    if (titleInput && !titleInput.value.trim()) {
                        let name = file.name.replace(/\.[^.]+$/, '');
                        name = name.replace(/[_-]+/g, ' ');
                        titleInput.value = name;
                    }
                },
                submitForm() {
                    if (this.mobileUploading) return;

                    const form = document.getElementById('uploadForm');
                    const formData = new FormData(form);
                    const storageChoice = document.querySelector('[name="storage_provider"]')?.value || 'local';
                    const videoFileName = this.videoFile ? this.videoFile.name : 'Video';

                    // Mobile fallback: mobile browsers block or suspend popup
                    // windows, so upload inline on this page instead. The
                    // desktop popup upload manager below is left untouched.
                    if (window.matchMedia('(max-width: 767px), (pointer: coarse)').matches) {
                        this.uploadInline(form, formData, storageChoice, videoFileName);
                        return;
                    }

                    // Convert FormData to an array of entries so it can be cloned via postMessage
                    const entries = [];
                    for (let [key, value] of formData.entries()) {
                        entries.push([key, value]);
                    }

                    // Open popup. Unique window name per upload so starting another
                    // upload does not reuse/replace a popup that is still uploading.
                    const popupUrl = '{{ route("upload.popup") }}';
                    const popupName = 'UploadManager_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
                    const popup = window.open(popupUrl, popupName, 'width=450,height=300,toolbar=0,menubar=0,location=0,status=0,scrollbars=0,resizable=0');
                    
                    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Popup Blocked',
                            text: 'Please allow popups for this site to use the background upload manager.',
                            background: '#1a1a1a', color: '#fff', confirmButtonColor: '#e50914'
                        });
                        return;
                    }

                    // Wait for popup to signal it's ready, then send data.
                    // Only accept the message from THIS popup so multiple
                    // simultaneous uploads do not cross-talk.
                    const messageHandler = (event) => {
                        if (event.source === popup && event.data && event.data.type === 'POPUP_READY') {
                            popup.postMessage({
                                type: 'START_UPLOAD',
                                action: form.action,
                                formData: entries,
                                fileName: videoFileName,
                                storageChoice: storageChoice
                            }, '*');
                            window.removeEventListener('message', messageHandler);
                            
                            // Do not redirect the parent window, otherwise the File object reference
                            // is destroyed by the browser and the popup upload will fail halfway!
                            // The user can navigate away manually or we can show a success state.
                            form.reset();
                            this.step = 1;
                        }
                    };
                    window.addEventListener('message', messageHandler);

                    // Show success on main window
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#1a1a1a',
                        color: '#fff',
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Upload started!',
                        text: 'Your upload is starting in the background window.'
                    });

                    form.reset();
                    this.step = 1;
                },

                formatSize(bytes) {
                    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
                    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
                    if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
                    return bytes + ' B';
                },

                formatSpeed(bytesPerSec) {
                    if (bytesPerSec >= 1048576) return (bytesPerSec / 1048576).toFixed(1) + ' MB/s';
                    if (bytesPerSec >= 1024) return (bytesPerSec / 1024).toFixed(0) + ' KB/s';
                    return bytesPerSec.toFixed(0) + ' B/s';
                },

                formatEta(seconds) {
                    if (!isFinite(seconds) || seconds <= 0) return 'calculating...';
                    if (seconds < 60) return Math.ceil(seconds) + 's remaining';
                    if (seconds < 3600) return Math.ceil(seconds / 60) + 'm ' + Math.floor(seconds % 60) + 's remaining';
                    return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm remaining';
                },

                uploadInline(form, formData, storageChoice, fileName) {
                    this.mobileUploading = true;
                    this.mobileStatus = 'Preparing upload...';
                    this.mobileProgress = 0;
                    this.mobileEta = 'calculating...';
                    this.mobileLastLoaded = 0;
                    this.mobileLastTime = 0;

                    let wakeLock = null;
                    const wakeLockHandler = () => { if (!document.hidden && !wakeLock) requestWakeLock(); };
                    const requestWakeLock = () => {
                        if (!('wakeLock' in navigator)) return;
                        navigator.wakeLock.request('screen')
                            .then(lock => { wakeLock = lock; })
                            .catch(() => {});
                    };
                    const releaseWakeLock = () => {
                        document.removeEventListener('visibilitychange', wakeLockHandler);
                        if (wakeLock) { try { wakeLock.release(); } catch (e) {} wakeLock = null; }
                    };
                    requestWakeLock();
                    document.addEventListener('visibilitychange', wakeLockHandler);

                    // Pull raw File object directly from DOM to avoid Alpine Proxy wrappers breaking mobile Safari slice()
                    const videoFile = document.getElementById('video_file_input').files[0] || this.videoFile;
                    const isFileMode = (videoFile && videoFile instanceof File && videoFile.size > 0);

                    let uploadToken = '';
                    if (isFileMode) {
                        // Use size and auth ID for token to avoid iOS changing temp file names/dates
                        uploadToken = 'vid_{{ auth()->id() }}_' + videoFile.size;
                    } else {
                        const arr = new Uint32Array(2);
                        (window.crypto || window.msCrypto).getRandomValues(arr);
                        uploadToken = arr[0].toString(36) + arr[1].toString(36) + Date.now().toString(36);
                    }
                    
                    if (formData.has('upload_token')) {
                        formData.set('upload_token', uploadToken);
                    } else {
                        formData.append('upload_token', uploadToken);
                    }

                    const submitFinalForm = (finalFormData) => {
                        this.mobileStatus = 'Saving to database...';
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', form.action, true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Accept', 'application/json');

                        let syncTimer = null;
                        if (storageChoice === 'pixeldrain' && isFileMode) {
                            const url = PROGRESS_URL.replace('__TOKEN__', uploadToken);
                            const tick = () => {
                                fetch(url, { headers: { 'Accept': 'application/json' } })
                                    .then(res => res.ok ? res.json() : Promise.reject('HTTP ' + res.status))
                                    .then(data => {
                                        if (data.active) {
                                            this.mobileProgress = data.percent || this.mobileProgress;
                                            this.mobileStatus = data.phase || 'Syncing to Pixeldrain...';
                                            if (data.chunked_speed !== undefined) this.mobileSpeed = this.formatSpeed(data.chunked_speed);
                                            if (data.chunked_eta !== undefined) this.mobileEta = this.formatEta(data.chunked_eta);
                                        } else {
                                            this.mobileStatus = 'Finalizing...';
                                        }
                                        syncTimer = setTimeout(tick, 1000);
                                    })
                                    .catch(() => { syncTimer = setTimeout(tick, 2000); });
                            };
                            tick();
                        }

                        xhr.onload = () => {
                            if (syncTimer) clearTimeout(syncTimer);
                            releaseWakeLock();
                            this.mobileUploading = false;
                            if (xhr.status >= 200 && xhr.status < 300) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Upload Complete!',
                                    text: storageChoice === 'pixeldrain'
                                        ? 'Your video is uploaded. Processing continues in the background.'
                                        : 'Your video has been uploaded successfully.',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#e50914',
                                    background: '#1a1a1a',
                                    color: '#fff',
                                }).then(() => {
                                    window.location.href = '{{ route("admin.dashboard") }}';
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Upload Failed', text: 'Server returned an error: ' + xhr.status, confirmButtonColor: '#e50914', background: '#1a1a1a', color: '#fff' });
                            }
                        };
                        xhr.onerror = () => {
                            if (syncTimer) clearTimeout(syncTimer);
                            releaseWakeLock();
                            this.mobileUploading = false;
                            Swal.fire({ icon: 'error', title: 'Upload Failed', text: 'A network error occurred. Please try again.', confirmButtonColor: '#e50914', background: '#1a1a1a', color: '#fff' });
                        };
                        xhr.send(finalFormData);
                    };

                    if (isFileMode) {
                        // Mobile inline uploads use small 8MB chunks: a single large
                        // PUT can stall indefinitely on flaky cellular links, and
                        // mobile browsers cap concurrent connections. Desktop uses
                        // adaptive ~96MB chunks to minimize round-trips.
                        const isMobileUpload = window.matchMedia('(max-width: 767px), (pointer: coarse)').matches;
                        const chunkSize = isMobileUpload
                            ? 8 * 1024 * 1024
                            : Math.max(8 * 1024 * 1024, Math.min(32 * 1024 * 1024, Math.ceil(videoFile.size / 48)));
                        const totalChunks = Math.ceil(videoFile.size / chunkSize);
                        let currentChunk = 0;
                        let retryCount = 0;
                        const maxRetries = 20; // Increased to 20 for even better resilience on screen wake
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                        // Abortable fetch: flaky mobile links can stall without ever
                        // erroring, so a bare fetch would hang forever instead of
                        // falling back/retrying. Timeout everything small.
                        const fetchWithTimeout = (url, options, ms) => {
                            const controller = new AbortController();
                            const timer = setTimeout(() => controller.abort(), ms);
                            return fetch(url, Object.assign({}, options, { signal: controller.signal }))
                                .finally(() => clearTimeout(timer));
                        };
                        // Per-chunk transfer timeout, scaled to chunk size (assumes a
                        // minimum 256KB/s so it never fires on slow-but-working links).
                        const chunkTimeoutMs = Math.max(120000, Math.ceil(chunkSize / (256 * 1024)) * 1000);

                        const startLegacyUpload = () => {
                        const uploadNextChunk = () => {
                            if (currentChunk >= totalChunks) return;

                            const start = currentChunk * chunkSize;
                            const end = Math.min(start + chunkSize, videoFile.size);
                            const chunk = videoFile.slice(start, end);

                            const chunkData = new FormData();
                            chunkData.append('chunk', chunk, videoFile.name);
                            chunkData.append('chunk_index', currentChunk);
                            chunkData.append('total_chunks', totalChunks);
                            chunkData.append('upload_token', uploadToken);
                            chunkData.append('original_filename', videoFile.name);

                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', '/upload/chunk', true);
                            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                            xhr.setRequestHeader('Accept', 'application/json');
                            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                            xhr.timeout = chunkTimeoutMs;
                            xhr.ontimeout = () => handleChunkError();
                            
                            this.mobileStatus = 'Starting chunk ' + (currentChunk + 1) + ' of ' + totalChunks + '...';

                            xhr.upload.onprogress = (e) => {
                                if (e.lengthComputable) {
                                    const loadedTotal = start + e.loaded;
                                    this.mobileProgress = Math.round((loadedTotal / videoFile.size) * 100);
                                    this.mobileStatus = 'Uploading chunk ' + (currentChunk + 1) + ' of ' + totalChunks + ' (' + this.formatSize(loadedTotal) + ' / ' + this.formatSize(videoFile.size) + ')';

                                    const now = Date.now();
                                    if (!this.mobileLastTime) {
                                        this.mobileLastTime = now;
                                        this.mobileLastLoaded = loadedTotal;
                                    } else {
                                        const timeDiff = (now - this.mobileLastTime) / 1000;
                                        if (timeDiff > 0.5) {
                                            const bytesDiff = loadedTotal - this.mobileLastLoaded;
                                            if (bytesDiff > 0) {
                                                const currentSpeed = bytesDiff / timeDiff;
                                                this.mobileSpeed = this.formatSpeed(currentSpeed);
                                                this.mobileEta = this.formatEta((videoFile.size - loadedTotal) / currentSpeed);
                                            }
                                            this.mobileLastTime = now;
                                            this.mobileLastLoaded = loadedTotal;
                                        }
                                    }
                                }
                            };

                            const handleChunkError = () => {
                                retryCount++;
                                this.mobileStatus = 'Network error (Code ' + xhr.status + '). Retrying chunk ' + (currentChunk + 1) + ' (' + retryCount + '/' + maxRetries + ')...';
                                if (retryCount > maxRetries) {
                                    this.mobileUploading = false;
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Upload Failed',
                                        text: 'Connection dropped (Code: ' + xhr.status + '). Please refresh the page, select the exact same file, and it will instantly resume!',
                                        confirmButtonColor: '#e50914',
                                        background: '#1a1a1a',
                                        color: '#fff'
                                    });
                                } else {
                                    setTimeout(() => uploadNextChunk(), 3000);
                                }
                            };

                            xhr.onload = () => {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    retryCount = 0;
                                    let res = {};
                                    try { res = JSON.parse(xhr.responseText); } catch (e) {}

                                    if (res.status === 'completed') {
                                        formData.delete('video_file');
                                        formData.append('original_filename', videoFile.name);
                                        submitFinalForm(formData);
                                    } else {
                                        currentChunk++;
                                        uploadNextChunk();
                                    }
                                } else {
                                    handleChunkError();
                                }
                            };
                            xhr.onerror = () => handleChunkError();
                            xhr.send(chunkData);
                        };

                        fetchWithTimeout('/upload/resume-check?upload_token=' + uploadToken + '&total_chunks=' + totalChunks + '&original_filename=' + encodeURIComponent(videoFile.name) + '&_=' + Date.now(), {}, 15000)
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.status === 'completed') {
                                    // All chunks are already on the server from a
                                    // previous session. Let the user decide: reuse
                                    // them, or wipe them and upload from scratch.
                                    this.askResume = true;
                                    this.askResumeSubmit = () => {
                                        formData.delete('video_file');
                                        formData.append('original_filename', videoFile.name);
                                        this.mobileProgress = 100;
                                        this.mobileStatus = 'Upload complete. Saving to database...';
                                        submitFinalForm(formData);
                                    };
                                    this.askResumeStartFresh = () => {
                                        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                                        const body = new URLSearchParams();
                                        body.append('upload_token', uploadToken);
                                        fetchWithTimeout('/upload/reset', {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                            body: body
                                        }, 15000)
                                        .then(() => {
                                            this.askResume = false;
                                            this.mobileProgress = 0;
                                            this.mobileStatus = '';
                                            currentChunk = 0;
                                            uploadNextChunk();
                                        })
                                        .catch(err => {
                                            console.warn('Reset failed, starting from 0 anyway', err);
                                            this.askResume = false;
                                            currentChunk = 0;
                                            uploadNextChunk();
                                        });
                                    };
                                    return;
                                }
                                if (data && data.uploaded_chunks) {
                                    currentChunk = data.uploaded_chunks;
                                    const loadedTotal = currentChunk * chunkSize;
                                    this.mobileProgress = Math.round((loadedTotal / videoFile.size) * 100);
                                    this.mobileStatus = 'Uploading chunk ' + (currentChunk + 1) + ' of ' + totalChunks + ' (' + this.formatSize(loadedTotal) + ' / ' + this.formatSize(videoFile.size) + ')';
                                }
                                uploadNextChunk();
                            })
                            .catch(err => {
                                uploadNextChunk();
                            });
                        };

                        // Cloudflare R2 direct upload: browser PUTs each chunk
                        // straight to Cloudflare's edge (MB/s even from far away),
                        // calling back only to mint presigned URLs and confirm
                        // each chunk. Falls back to the server chunk upload above.
                        let r2Completed = false;
                        let r2Aborted = false;
                        const r2DoneSet = {};
                        const r2InFlight = {};
                        const r2Xhr = {};
                        const r2LastTick = {};
                        const r2Retries = {};
                        let r2BytesDone = 0;
                        const R2_WORKERS = isMobileUpload ? 2 : 4;
                        const R2_STALL_MS = 45000;
                        const R2_MAX_RETRIES = 15;
                        const r2Watchdog = () => {
                            const now = Date.now();
                            for (const index in r2Xhr) {
                                const last = r2LastTick[index];
                                if (last && now - last > R2_STALL_MS) {
                                    try { r2Xhr[index].abort(); } catch (e) {}
                                }
                            }
                            if (!r2Completed && !r2Aborted) setTimeout(r2Watchdog, 5000);
                        };

                        const presignChunk = (index) => {
                            const body = new URLSearchParams();
                            body.append('upload_token', uploadToken);
                            body.append('chunk_index', index);
                            body.append('total_chunks', totalChunks);
                            body.append('original_filename', videoFile.name);
                            return fetchWithTimeout('/upload/presign', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: body.toString()
                            }, 15000).then(res => res.ok ? res.json() : Promise.reject('HTTP ' + res.status));
                        };

                        const confirmChunk = (index) => {
                            const body = new URLSearchParams();
                            body.append('upload_token', uploadToken);
                            body.append('chunk_index', index);
                            return fetchWithTimeout('/upload/chunk-done', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: body.toString()
                            }, 15000);
                        };

                        const updateR2Progress = () => {
                            let loadedTotal = r2BytesDone;
                            for (const k in r2InFlight) loadedTotal += r2InFlight[k] || 0;
                            loadedTotal = Math.min(loadedTotal, videoFile.size);
                            this.mobileProgress = Math.round((loadedTotal / videoFile.size) * 100);
                            this.mobileStatus = 'Uploading to Cloudflare edge (' + this.formatSize(loadedTotal) + ' / ' + this.formatSize(videoFile.size) + ')';

                            const now = Date.now();
                            if (!this.mobileLastTime) {
                                this.mobileLastTime = now;
                                this.mobileLastLoaded = loadedTotal;
                            } else {
                                const timeDiff = (now - this.mobileLastTime) / 1000;
                                if (timeDiff > 0.5) {
                                    const bytesDiff = loadedTotal - this.mobileLastLoaded;
                                    if (bytesDiff > 0) {
                                        const currentSpeed = bytesDiff / timeDiff;
                                        this.mobileSpeed = this.formatSpeed(currentSpeed);
                                        this.mobileEta = this.formatEta((videoFile.size - loadedTotal) / currentSpeed);
                                    }
                                    this.mobileLastTime = now;
                                    this.mobileLastLoaded = loadedTotal;
                                }
                            }
                        };

                        const submitWhenR2Done = () => {
                            if (r2Completed || r2Aborted) return;
                            r2Completed = true;
                            formData.delete('video_file');
                            formData.append('original_filename', videoFile.name);
                            submitFinalForm(formData);
                        };

                        const markR2ChunkDone = (index) => {
                            if (r2Aborted) return;
                            r2DoneSet[index] = true;
                            if (Object.keys(r2DoneSet).length >= totalChunks) {
                                submitWhenR2Done();
                            }
                        };

                        const failR2Upload = (index, done) => {
                            r2Aborted = true;
                            r2Completed = true;
                            for (const k in r2Xhr) { try { r2Xhr[k].abort(); } catch (e) {} }
                            this.mobileUploading = false;
                            Swal.fire({
                                icon: 'error',
                                title: 'Upload Failed',
                                text: 'Chunk ' + (index + 1) + ' of ' + totalChunks + ' failed to upload after ' + R2_MAX_RETRIES + ' attempts. Refresh the page and select the same file to resume instantly.',
                                confirmButtonColor: '#e50914',
                                background: '#1a1a1a',
                                color: '#fff'
                            });
                            if (done) done();
                        };

                        const retryChunk = (index, done) => {
                            if (r2Aborted) { if (done) done(); return; }
                            r2Retries[index] = (r2Retries[index] || 0) + 1;
                            if (r2Retries[index] > R2_MAX_RETRIES) {
                                failR2Upload(index, done);
                                return;
                            }
                            setTimeout(() => uploadChunk(index, done), 3000);
                        };

                        const uploadChunk = (index, done) => {
                            if (r2Aborted) { if (done) done(); return; }
                            if (index >= totalChunks) { if (done) done(); return; }
                            if (r2DoneSet[index]) { if (done) done(); return; }
                            const start = index * chunkSize;
                            const end = Math.min(start + chunkSize, videoFile.size);
                            const chunk = videoFile.slice(start, end);

                            presignChunk(index)
                                .then(res => {
                                    if (res.already_done) {
                                        r2BytesDone += (end - start);
                                        updateR2Progress();
                                        markR2ChunkDone(index);
                                        if (done) done();
                                        return;
                                    }
                                    if (!res.url) throw new Error('No presigned URL returned');

                                    const xhr = new XMLHttpRequest();
                                    r2Xhr[index] = xhr;
                                    r2LastTick[index] = Date.now();
                                    xhr.open('PUT', res.url, true);
                                    xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                                    xhr.timeout = chunkTimeoutMs;
                                    xhr.upload.onprogress = (e) => {
                                        if (e.lengthComputable) {
                                            r2LastTick[index] = Date.now();
                                            r2InFlight[index] = e.loaded;
                                            updateR2Progress();
                                        }
                                    };
                                    xhr.onload = () => {
                                        delete r2Xhr[index];
                                        if (xhr.status >= 200 && xhr.status < 300) {
                                            confirmChunk(index)
                                                .then(() => {
                                                    r2BytesDone += (end - start);
                                                    delete r2InFlight[index];
                                                    updateR2Progress();
                                                    markR2ChunkDone(index);
                                                    if (done) done();
                                                })
                                                .catch(() => retryChunk(index, done));
                                        } else {
                                            retryChunk(index, done);
                                        }
                                    };
                                    xhr.onerror = () => {
                                        delete r2Xhr[index];
                                        delete r2InFlight[index];
                                        retryChunk(index, done);
                                    };
                                    xhr.onabort = () => {
                                        delete r2Xhr[index];
                                        delete r2InFlight[index];
                                        if (!r2Aborted) retryChunk(index, done);
                                    };
                                    xhr.ontimeout = () => {
                                        delete r2Xhr[index];
                                        delete r2InFlight[index];
                                        retryChunk(index, done);
                                    };
                                    xhr.send(chunk);
                                })
                                .catch(() => retryChunk(index, done));
                        };

                        const r2Worker = () => {
                            if (r2Completed || r2Aborted) return;
                            const index = currentChunk++;
                            if (index >= totalChunks) return;
                            uploadChunk(index, () => { if (!r2Completed && !r2Aborted) r2Worker(); });
                        };

                        const probeAndStartR2 = () => {
                            const probeBody = new URLSearchParams();
                            probeBody.append('upload_token', uploadToken);
                            probeBody.append('chunk_index', 0);
                            probeBody.append('total_chunks', totalChunks);
                            probeBody.append('original_filename', videoFile.name);
                            fetchWithTimeout('/upload/presign', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: probeBody.toString()
                            }, 15000)
                                .then(res => res.ok ? res.json() : Promise.reject('HTTP ' + res.status))
                                .then(() => {
                                    r2Watchdog();
                                    for (let w = 0; w < R2_WORKERS; w++) r2Worker();
                                })
                                .catch(() => {
                                    startLegacyUpload();
                                });
                        };

                        fetchWithTimeout('/upload/resume-check?upload_token=' + uploadToken + '&total_chunks=' + totalChunks + '&original_filename=' + encodeURIComponent(videoFile.name) + '&_=' + Date.now(), {}, 15000)
                            .then(res => res.ok ? res.json() : Promise.reject('HTTP ' + res.status))
                            .then(data => {
                                if (data && data.status === 'completed') {
                                    submitWhenR2Done();
                                    return;
                                }
                                if (data && data.uploaded_chunks > 0) {
                                    currentChunk = data.uploaded_chunks;
                                    for (let i = 0; i < currentChunk; i++) r2DoneSet[i] = true;
                                    r2BytesDone = currentChunk * chunkSize;
                                    updateR2Progress();
                                    this.mobileProgress = Math.round((r2BytesDone / videoFile.size) * 100);
                                    this.mobileStatus = 'Resuming upload (' + this.formatSize(r2BytesDone) + ' / ' + this.formatSize(videoFile.size) + ')...';
                                }
                                probeAndStartR2();
                            })
                            .catch(() => probeAndStartR2());
                    } else {
                        submitFinalForm(formData);
                    }
                }
            }
        }

        // ---- Dynamic audio-track rows (step 4) ----
        function reindexAudioRows() {
            const rows = document.querySelectorAll('#audio-tracks-container .audio-track-row');
            rows.forEach((row, i) => {
                row.querySelector('.audio-language').name = 'audio_language[' + i + ']';
                row.querySelector('.audio-file').name = 'audio_files[' + i + ']';
                row.querySelector('.audio-default').name = 'default_audio[' + i + ']';
            });
        }

        function addAudioTrack() {
            const container = document.getElementById('audio-tracks-container');
            const template = document.getElementById('audio-track-template');
            if (!container || !template) return;
            const row = template.content.firstElementChild.cloneNode(true);
            container.appendChild(row);
            reindexAudioRows();
        }

        function removeAudioTrack(btn) {
            const row = btn.closest('.audio-track-row');
            if (row) row.remove();
            reindexAudioRows();
        }

        document.addEventListener('DOMContentLoaded', addAudioTrack);
    </script>
</x-admin-layout>
