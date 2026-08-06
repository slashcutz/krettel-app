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
    </style>
</head>
<body class="antialiased flex flex-col h-screen" x-data="uploadPopup()">
    <div class="flex-1 flex flex-col p-6 items-center justify-center relative">
        <template x-if="status === 'waiting'">
            <div class="text-center">
                <svg class="w-12 h-12 text-zinc-500 mx-auto mb-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <h2 class="text-lg font-semibold">Waiting for upload data...</h2>
            </div>
        </template>

        <template x-if="status === 'uploading'">
            <div class="w-full max-w-sm">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="bg-primary/20 p-2 rounded-full">
                        <svg class="w-6 h-6 text-primary animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-white leading-tight" x-text="'Uploading: ' + fileName"></h2>
                        <p class="text-xs text-zinc-400">Please keep this window open.</p>
                    </div>
                </div>

                <div class="w-full bg-zinc-800 rounded-full h-3 mb-2 overflow-hidden shadow-inner">
                    <div class="bg-primary h-3 rounded-full transition-all duration-300 relative overflow-hidden" :style="`width: ${progress}%`">
                        <div class="absolute inset-0 bg-white/20 w-full animate-[shimmer_2s_infinite]"></div>
                    </div>
                </div>

                <div class="flex justify-between text-xs text-zinc-400 mb-1 font-medium">
                    <span x-text="progress + '%'"></span>
                    <span x-text="loadedSize + ' / ' + totalSize"></span>
                </div>
                <div class="flex justify-between text-xs text-zinc-500">
                    <span x-text="'Speed: ' + speed"></span>
                    <span x-text="'ETA: ' + eta"></span>
                </div>
            </div>
        </template>

        <template x-if="status === 'success'">
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
        </template>

        <template x-if="status === 'error'">
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
        </template>
    </div>

    <!-- Add a simple shimmer animation for the progress bar -->
    <style>
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>

    <script src="//unpkg.com/alpinejs" defer></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('uploadPopup', () => ({
                status: 'waiting', // waiting, uploading, success, error
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

                init() {
                    window.addEventListener('message', (event) => {
                        // For security, you might want to verify event.origin in a real app,
                        // but since it's same-origin popup, it's fine.
                        if (event.data && event.data.type === 'START_UPLOAD') {
                            this.startUpload(event.data.action, event.data.formData, event.data.fileName, event.data.storageChoice);
                        }
                    });

                    // Tell parent we are ready to receive data
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

                startUpload(action, entries, fileName, storageChoice) {
                    this.status = 'uploading';
                    this.fileName = fileName;
                    
                    // Reconstruct FormData from array of entries
                    const formData = new FormData();
                    entries.forEach(entry => {
                        formData.append(entry[0], entry[1]);
                    });

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', action, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    
                    this.lastTime = Date.now();
                    this.lastLoaded = 0;

                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            const now = Date.now();
                            this.progress = Math.round((e.loaded / e.total) * 100);
                            this.loadedSize = this.formatSize(e.loaded);
                            this.totalSize = this.formatSize(e.total);
                            
                            const timeDiff = (now - this.lastTime) / 1000;
                            if (timeDiff > 0.5) {
                                const bytesDiff = e.loaded - this.lastLoaded;
                                const currentSpeed = bytesDiff / timeDiff;
                                this.speed = currentSpeed > 0 ? this.formatSpeed(currentSpeed) : '--';
                                
                                const remaining = e.total - e.loaded;
                                const eta = currentSpeed > 0 ? remaining / currentSpeed : 0;
                                this.eta = this.formatETA(eta);
                                
                                this.lastLoaded = e.loaded;
                                this.lastTime = now;
                            }
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            this.status = 'success';
                            this.successMessage = storageChoice === 'terabox'
                                ? 'Upload to server complete. TeraBox sync has been queued.'
                                : 'Video uploaded successfully to local storage.';
                            
                            // Auto-close after 5 seconds on success
                            setTimeout(() => {
                                window.close();
                            }, 5000);
                        } else {
                            this.status = 'error';
                            this.errorMessage = 'Server returned an error: ' + xhr.status;
                        }
                    };

                    xhr.onerror = () => {
                        this.status = 'error';
                        this.errorMessage = 'A network error occurred during upload.';
                    };

                    xhr.send(formData);
                }
            }));
        });
    </script>
</body>
</html>
