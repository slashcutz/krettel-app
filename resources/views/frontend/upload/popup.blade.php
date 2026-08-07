<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Upload Manager - {{ config('app.name', 'Krettel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])

    <style>
        body { background-color: #09090b; color: #fff; overflow: hidden; font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #27272a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #3f3f46; }
    </style>
</head>
<body class="antialiased flex flex-col h-screen" x-data="uploadPopup()">
    <div class="flex-1 flex flex-col p-6 min-h-0">

        <template x-if="status === 'waiting'">
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <svg class="w-12 h-12 text-zinc-500 mx-auto mb-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    <h2 class="text-lg font-semibold">Waiting for upload data...</h2>
                </div>
            </div>
        </template>

        <template x-if="status === 'uploading'">
            <div class="flex-1 flex items-center justify-center">
                <div class="w-full max-w-sm">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="bg-primary/20 p-2 rounded-full">
                            <svg class="w-6 h-6 text-primary animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        </div>
                        <div>
                            <h2 class="font-bold text-white leading-tight" x-text="syncing ? ('Syncing to Pixeldrain: ' + fileName) : ('Uploading: ' + fileName)"></h2>
                            <p class="text-xs text-zinc-400" x-text="syncing ? (syncPhase || 'Pushing to Pixeldrain cloud. Keep this window open.') : 'Please keep this window open.'"></p>
                        </div>
                    </div>

                    <div class="w-full bg-zinc-800 rounded-full h-3 mb-2 overflow-hidden shadow-inner">
                        <div class="bg-primary h-3 rounded-full transition-all duration-300 relative overflow-hidden" :style="`width: ${displayProgress}%`">
                            <div class="absolute inset-0 bg-white/20 w-full animate-[shimmer_2s_infinite]"></div>
                        </div>
                    </div>

                    <div class="flex justify-between text-xs text-zinc-400 mb-1 font-medium">
                        <span x-text="displayProgress + '%'"></span>
                        <span x-text="displaySize"></span>
                    </div>
                    <div class="flex justify-between text-xs text-zinc-500">
                        <span x-text="syncing ? ('Speed: ' + syncSpeed) : ('Speed: ' + speed)"></span>
                        <span x-text="syncing ? ('ETA: ' + syncEta) : ('ETA: ' + eta)"></span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Live processing console: polls /upload/status/{id} and tails the log file -->
        <template x-if="status === 'processing'">
            <div class="flex-1 flex flex-col min-h-0">
                <!-- header -->
                <div class="flex items-center justify-between mb-3 gap-3">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="bg-primary/20 p-2 rounded-full shrink-0">
                            <svg class="w-5 h-5 text-primary" :class="isDone ? '' : 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-bold text-white leading-tight truncate" x-text="processingTitle"></h2>
                            <p class="text-xs text-zinc-400 truncate" x-text="phaseLabel()"></p>
                        </div>
                    </div>
                    <button @click="window.close()" class="text-xs text-zinc-400 hover:text-white border border-zinc-700 rounded px-2 py-1 shrink-0">Close</button>
                </div>

                <!-- status chips -->
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="text-[11px] px-2 py-1 rounded-full bg-green-900/40 text-green-400">Uploaded</span>
                    <span class="text-[11px] px-2 py-1 rounded-full font-medium" :class="phaseChipClass()" x-text="phaseLabel()"></span>
                    <template x-if="isDone && phase === 'ready'">
                        <span class="text-[11px] px-2 py-1 rounded-full bg-green-900/40 text-green-400">Audio switcher ready</span>
                    </template>
                    <template x-if="isDone && phase === 'failed'">
                        <span class="text-[11px] px-2 py-1 rounded-full bg-red-900/40 text-red-400">See log below</span>
                    </template>
                </div>

                <!-- console / log file panel -->
                <div class="flex-1 min-h-0 rounded-lg border border-zinc-800 bg-black/60 overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-800 bg-zinc-900/60">
                        <span class="text-[11px] text-zinc-400 font-medium tracking-wide">LOG — storage/logs/krettel-*.log</span>
                        <button @click="copyLog()" class="text-[11px] text-primary hover:underline" x-text="copied ? 'Copied!' : 'Copy log'"></button>
                    </div>
                    <div x-ref="logPanel" class="flex-1 overflow-y-auto p-3 font-mono text-[11px] leading-relaxed">
                        <div x-show="logs.length === 0" class="text-zinc-500 italic">Waiting for processing log entries...</div>
                        <template x-for="line in logs" :key="line.time + line.message">
                            <div class="mb-1 break-words">
                                <span class="text-zinc-600" x-text="line.time"></span>
                                <span class="text-zinc-500 ml-2" x-text="'[' + line.level + ']'"></span>
                                <span class="ml-2" :class="logColor(line.level)" x-text="line.message"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- done footer -->
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="window.close()" class="text-[11px] px-3 py-1.5 rounded-lg bg-zinc-800 text-white hover:bg-zinc-700 border border-zinc-700">Close Window</button>
                    <a x-show="isDone && (phase === 'ready' || phase === 'terabox' || phase === 'pixeldrain')" :href="slug ? '{{ url('/video') }}/' + slug : '{{ route('admin.videos.index') }}'" target="_blank" class="text-[11px] px-3 py-1.5 rounded-lg bg-primary text-white font-medium hover:bg-red-600">Open Video</a>
                </div>
            </div>
        </template>

        <template x-if="status === 'success'">
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center w-full max-w-sm">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-900/50 mb-4">
                        <svg class="h-10 w-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">Upload Complete!</h2>
                    <p class="text-zinc-400 text-sm mb-6" x-text="successMessage"></p>
                    <button @click="window.close()" class="w-full bg-zinc-800 hover:bg-zinc-700 text-white font-medium py-2 px-4 rounded-lg transition-colors border border-zinc-700">
                        Close Window
                    </button>
                </div>
            </div>
        </template>

        <template x-if="status === 'error'">
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center w-full max-w-sm">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-900/50 mb-4">
                        <svg class="h-10 w-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">Upload Failed</h2>
                    <p class="text-zinc-400 text-sm mb-6" x-text="errorMessage"></p>
                    <button @click="window.close()" class="w-full bg-zinc-800 hover:bg-zinc-700 text-white font-medium py-2 px-4 rounded-lg transition-colors border border-zinc-700">
                        Close Window
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- Add a simple shimmer animation for the progress bar -->
    <style>
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>

    <script>
        const STATUS_URL = '{{ route('upload.status', '__ID__') }}';
        const PROGRESS_URL = '{{ route('upload.progress', '__TOKEN__') }}';
    </script>

    <script src="//unpkg.com/alpinejs" defer></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('uploadPopup', () => ({
                status: 'waiting', // waiting, uploading, processing, success, error
                fileName: 'Video File',
                progress: 0,
                loadedSize: '0 B',
                totalSize: '0 B',
                speed: '--',
                eta: 'calculating...',
                successMessage: '',
                errorMessage: '',
                lastLoaded: 0,
                lastTime: 0,

                // processing console state
                videoId: null,
                slug: null,
                processingTitle: '',
                phase: 'uploading',
                isDone: false,
                logs: [],
                copied: false,
                pollTimer: null,

                // pixeldrain sync state (tracked via cache + token, not video id)
                uploadToken: '',
                syncing: false,
                syncProgress: 0,
                syncUploaded: 0,
                syncTotal: 0,
                syncPhase: '',
                syncLastLoaded: 0,
                syncLastTime: 0,
                syncSpeed: '--',
                syncEta: 'calculating...',
                syncPollTimer: null,
                storageChoice: '',

                init() {
                    window.addEventListener('message', (event) => {
                        if (event.source === window.opener && event.data && event.data.type === 'START_UPLOAD') {
                            this.startUpload(event.data.action, event.data.formData, event.data.fileName, event.data.storageChoice);
                        }
                    });

                    if (window.opener) {
                        window.opener.postMessage({ type: 'POPUP_READY' }, '*');
                    }
                },

                formatSize(bytes) {
                    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
                    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
                    if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
                    return bytes + ' B';
                },

                formatETA(seconds) {
                    if (!isFinite(seconds) || seconds <= 0) return 'calculating...';
                    if (seconds < 60) return Math.ceil(seconds) + 's remaining';
                    if (seconds < 3600) return Math.ceil(seconds / 60) + 'm ' + Math.floor(seconds % 60) + 's remaining';
                    return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm remaining';
                },

                formatSpeed(bytesPerSec) {
                    if (bytesPerSec >= 1048576) return (bytesPerSec / 1048576).toFixed(1) + ' MB/s';
                    if (bytesPerSec >= 1024) return (bytesPerSec / 1024).toFixed(0) + ' KB/s';
                    return bytesPerSec.toFixed(0) + ' B/s';
                },

                // Pixeldrain sync bar shows server->Pixeldrain progress once the
                // browser->server leg is done; otherwise the classic upload bar.
                get displayProgress() {
                    return this.syncing ? this.syncProgress : this.progress;
                },

                get displaySize() {
                    if (this.syncing) return this.formatSize(this.syncUploaded) + ' / ' + this.formatSize(this.syncTotal);
                    return this.loadedSize + ' / ' + this.totalSize;
                },

                phaseLabel() {
                    const map = {
                        uploading: 'Uploading to server',
                        pending: 'Waiting in queue',
                        processing: 'Transcoding to HLS (multi-audio)',
                        'terabox-uploading': 'Syncing to TeraBox',
                        terabox: 'Stored on TeraBox',
                        'pixeldrain-uploading': 'Syncing to Pixeldrain',
                        pixeldrain: 'Stored on Pixeldrain',
                        ready: 'Ready — HLS multi-audio',
                        failed: 'Processing failed',
                    };
                    return map[this.phase] || this.phase || '...';
                },

                phaseChipClass() {
                    if (this.phase === 'ready' || this.phase === 'terabox' || this.phase === 'pixeldrain') return 'bg-green-900/40 text-green-400';
                    if (this.phase === 'failed') return 'bg-red-900/40 text-red-400';
                    return 'bg-blue-900/40 text-blue-300 animate-pulse';
                },

                logColor(level) {
                    if (level === 'error') return 'text-red-400';
                    if (level === 'warning') return 'text-amber-400';
                    if (level === 'debug') return 'text-zinc-500';
                    return 'text-zinc-300';
                },

                scrollLog() {
                    const el = this.$refs.logPanel;
                    if (el) el.scrollTop = el.scrollHeight;
                },

                copyLog() {
                    const text = this.logs.map(l => '[' + l.time + '] [' + l.level + '] ' + l.message).join('\n');
                    if (!text) return;
                    navigator.clipboard && navigator.clipboard.writeText(text).then(() => {
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 1500);
                    });
                },

                pollStatus() {
                    const url = STATUS_URL.replace('__ID__', this.videoId);

                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(res => res.ok ? res.json() : Promise.reject('HTTP ' + res.status))
                        .then(data => {
                            this.phase = data.state || this.phase;
                            this.slug = data.slug || this.slug;
                            console.log('[upload] status:', data.state, '| hls_status:', data.hls_status, '| video_url:', data.video_url);

                            const fresh = data.logs || [];
                            if (fresh.length < this.logs.length) {
                                this.logs = fresh;
                            } else if (fresh.length > this.logs.length) {
                                fresh.slice(this.logs.length).forEach(line => {
                                    this.logs.push(line);
                                    console.log('[upload]', line.time, line.level, line.message);
                                });
                            }

                            this.$nextTick(() => this.scrollLog());

                            if (data.done) {
                                this.isDone = true;
                                console.log('[upload] finished, final state:', data.state);
                                return;
                            }

                            this.pollTimer = setTimeout(() => this.pollStatus(), 2500);
                        })
                        .catch(err => {
                            console.warn('[upload] status poll failed:', err);
                            this.pollTimer = setTimeout(() => this.pollStatus(), 3000);
                        });
                },

                pollPixeldrainSync() {
                    if (this.syncPollTimer) return;

                    const url = PROGRESS_URL.replace('__TOKEN__', this.uploadToken);

                    const tick = () => {
                        fetch(url, { headers: { 'Accept': 'application/json' } })
                            .then(res => res.ok ? res.json() : Promise.reject('HTTP ' + res.status))
                            .then(data => {
                                if (data.active) {
                                    this.syncing = true;
                                    this.syncProgress = data.percent || 0;
                                    this.syncUploaded = data.uploaded || 0;
                                    this.syncTotal = data.total || 0;
                                    this.syncPhase = data.phase || '';

                                    if (data.chunked_speed !== undefined) {
                                        this.syncSpeed = this.formatSpeed(data.chunked_speed);
                                    }
                                    if (data.chunked_eta !== undefined) {
                                        this.syncEta = this.formatETA(data.chunked_eta);
                                    }

                                    this.syncPollTimer = setTimeout(tick, 1000);
                                } else {
                                    // No active progress entry yet (sync not started)
                                    // or it was cleared (done). Keep polling either way.
                                    this.syncPollTimer = setTimeout(tick, 1000);
                                }
                            })
                            .catch(() => {
                                this.syncPollTimer = setTimeout(tick, 2000);
                            });
                    };

                    tick();
                },

                stopPixeldrainSync() {
                    if (this.syncPollTimer) {
                        clearTimeout(this.syncPollTimer);
                        this.syncPollTimer = null;
                    }
                },

                startUpload(action, entries, fileName, storageChoice) {
                    this.status = 'uploading';
                    this.fileName = fileName;
                    this.syncing = false;

                    const formData = new FormData();
                    let videoFile = null;
                    entries.forEach(entry => {
                        formData.append(entry[0], entry[1]);
                        if (entry[0] === 'video_file') {
                            videoFile = entry[1];
                        }
                    });

                    if (storageChoice === 'pixeldrain') {
                        const arr = new Uint32Array(2);
                        (window.crypto || window.msCrypto).getRandomValues(arr);
                        this.uploadToken = arr[0].toString(36) + arr[1].toString(36) + Date.now().toString(36);
                        formData.append('upload_token', this.uploadToken);
                    }

                    this.lastTime = Date.now();
                    this.lastLoaded = 0;
                    this.syncLastTime = 0;
                    this.syncLastLoaded = 0;

                    const isFileMode = (videoFile && videoFile instanceof File && videoFile.size > 0);

                    const submitFinalForm = (finalFormData) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', action, true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Accept', 'application/json');

                        xhr.onload = () => {
                            this.stopPixeldrainSync();
                            if (xhr.status >= 200 && xhr.status < 300) {
                                let data = {};
                                try { data = JSON.parse(xhr.responseText); } catch (e) {}
                                if (data && data.video_id) {
                                    this.videoId = data.video_id;
                                    this.slug = data.slug || null;
                                    this.processingTitle = data.title || fileName;
                                    this.status = 'processing';
                                    this.phase = 'uploading';
                                    this.pollStatus();
                                } else {
                                    this.status = 'success';
                                    this.successMessage = storageChoice === 'terabox'
                                        ? 'Upload to server complete. TeraBox sync has been queued.'
                                        : storageChoice === 'pixeldrain' ? 'Video uploaded successfully to Pixeldrain.' : 'Video uploaded successfully to local storage.';
                                    setTimeout(() => window.close(), 5000);
                                }
                            } else {
                                this.status = 'error';
                                this.errorMessage = 'Server returned an error: ' + xhr.status;
                            }
                        };
                        xhr.onerror = () => {
                            this.stopPixeldrainSync();
                            this.status = 'error';
                            this.errorMessage = 'A network error occurred during final save.';
                        };

                        if (storageChoice === 'pixeldrain' && isFileMode) {
                            this.pollPixeldrainSync();
                        }
                        xhr.send(finalFormData);
                    };

                    if (isFileMode) {
                        const chunkSize = 5 * 1024 * 1024; // 5MB
                        const totalChunks = Math.ceil(videoFile.size / chunkSize);
                        let currentChunk = 0;

                        const uploadNextChunk = () => {
                            if (currentChunk >= totalChunks) return;
                            const start = currentChunk * chunkSize;
                            const end = Math.min(start + chunkSize, videoFile.size);
                            const chunk = videoFile.slice(start, end);

                            const chunkData = new FormData();
                            chunkData.append('chunk', chunk);
                            chunkData.append('chunk_index', currentChunk);
                            chunkData.append('total_chunks', totalChunks);
                            chunkData.append('upload_token', this.uploadToken || 'none');
                            chunkData.append('original_filename', videoFile.name);

                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', '/upload/chunk', true);
                            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                            xhr.setRequestHeader('Accept', 'application/json');
                            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);

                            xhr.upload.onprogress = (e) => {
                                if (e.lengthComputable) {
                                    const now = Date.now();
                                    const loadedTotal = start + e.loaded;
                                    this.progress = Math.round((loadedTotal / videoFile.size) * 100);
                                    this.loadedSize = this.formatSize(loadedTotal);
                                    this.totalSize = this.formatSize(videoFile.size);

                                    const timeDiff = (now - this.lastTime) / 1000;
                                    if (timeDiff > 0.5) {
                                        const bytesDiff = loadedTotal - this.lastLoaded;
                                        const currentSpeed = bytesDiff / timeDiff;
                                        this.speed = currentSpeed > 0 ? this.formatSpeed(currentSpeed) : '--';
                                        const remaining = videoFile.size - loadedTotal;
                                        const eta = currentSpeed > 0 ? remaining / currentSpeed : 0;
                                        this.eta = this.formatETA(eta);

                                        this.lastLoaded = loadedTotal;
                                        this.lastTime = now;
                                    }
                                }
                            };

                            xhr.onload = () => {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    let res = {};
                                    try { res = JSON.parse(xhr.responseText); } catch (e) {}

                                    if (res.status === 'completed') {
                                        formData.delete('video_file');
                                        formData.append('stitched_file_path', res.stitched_file_path);
                                        submitFinalForm(formData);
                                    } else {
                                        currentChunk++;
                                        uploadNextChunk();
                                    }
                                } else {
                                    setTimeout(() => uploadNextChunk(), 3000);
                                }
                            };
                            xhr.onerror = () => setTimeout(() => uploadNextChunk(), 3000);
                            xhr.send(chunkData);
                        };

                        uploadNextChunk();
                    } else {
                        submitFinalForm(formData);
                    }
                }
            }));
        });
    </script>
</body>
</html>
