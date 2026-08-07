<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $video['title'] ?? 'Watch Video' }} - {{ config('app.name', 'Krettel') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Video.js CSS -->
        <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
        
        <!-- hls.js for HLS (TeraBox) playback -->
        <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('myListToggle', (videoId = null) => ({
                    videoId: videoId,
                    inList: {{ $inMyList ? 'true' : 'false' }},
                    busy: false,

                    async toggle() {
                        if (this.busy) return;
                        this.busy = true;
                        try {
                            const res = await fetch("{{ route('list.toggle') }}", {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({ video_id: this.videoId })
                            });
                            const data = await res.json();
                            if (data.added !== undefined) {
                                this.inList = data.added;
                            }
                        } catch (e) {}
                        this.busy = false;
                    }
                }));
            });
        </script>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('videoPlayer', (id = null) => ({
                    isPlaying: false,
                    hasPlayed: false,
                    videoId: id,
                    isBuffering: false,
                    isMuted: false,
                    isFullscreen: false,
                    isPictureInPicture: false,
                    miniPlayer: false,
                    showSettings: false,
                    showControls: true,
                    controlsTimeout: null,
                    isDragging: false,
                    currentTime: 0,
                    duration: 0,
                    progress: 0,
                    speed: 1,
                    volume: 1,
                    captionsOn: false,
                    videoError: null,
                    watchTimeAccumulated: 0,
                    quality: -1,
                    levels: [],
                    audioTracks: [],
                    audioTrack: -1,
                    isDirectStream: false,

                    initPlayer() {
                        this.volume = this.$refs.video.volume;
                        // Attach HLS (m3u8) source via hls.js when the source is HLS.
                        this.attachHls();
                        // Start Heartbeat sync for analytics
                        setInterval(() => {
                            this.syncWatchTime();
                        }, 10000);

                        // Set duration once metadata is loaded
                        this.$refs.video.addEventListener('loadedmetadata', () => {
                            this.duration = this.$refs.video.duration;
                            this.syncContinueWatching();
                        });

                        // Track buffer state
                        this.$refs.video.addEventListener('waiting', () => {
                            this.isBuffering = true;
                        });
                        this.$refs.video.addEventListener('playing', () => {
                            this.isBuffering = false;
                            this.isPlaying = true;
                            this.hasPlayed = true;
                        });

                        // Track error state
                        this.$refs.video.addEventListener('error', () => {
                            this.videoError = 'This video could not be loaded. It may still be processing or the file is not available.';
                            this.isPlaying = false;
                        });

                        // Listen to browser fullscreen changes to sync orientation/controls
                        const onFsChange = () => {
                            this.isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement);
                            if (this.isFullscreen) {
                                document.body.style.overflow = 'hidden';
                                this.rotateToLandscape();
                            } else {
                                document.body.style.overflow = '';
                                this.unrotate();
                            }
                        };
                        document.addEventListener('fullscreenchange', onFsChange);
                        document.addEventListener('webkitfullscreenchange', onFsChange);

                        // ESC key fallback
                        document.addEventListener('keydown', (e) => {
                            if (e.key === 'Escape' && this.isFullscreen) {
                                e.preventDefault();
                                this.toggleFullscreen();
                            }
                        });

                        // Picture-in-Picture detection
                        if (this.$refs.video) {
                            this.$refs.video.addEventListener('enterpictureinpicture', () => {
                                this.isPictureInPicture = true;
                            });
                            this.$refs.video.addEventListener('leavepictureinpicture', () => {
                                this.isPictureInPicture = false;
                            });
                        }

                        // Keyboard shortcuts
                        this.bindKeyboardShortcuts();
                    },

                    attachHls() {
                        const video = this.$refs.video;
                        if (!video) return;
                        const source = video.querySelector('source');
                        if (!source) return;
                        const src = source.getAttribute('src');
                        if (source.getAttribute('type') !== 'application/x-mpegURL') return;
                        if (!src) return;

                        if (window.Hls && Hls.isSupported()) {
                            const hls = new Hls({
                                startLevel: 0,
                                capLevelToPlayerSize: false,
                                maxBufferLength: 30,
                                maxMaxBufferLength: 90,
                                backBufferLength: 30,
                                maxBufferHole: 0.5,
                                enableWorker: true,
                                lowLatencyMode: false,
                                fragLoadingTimeOut: 15000,
                                manifestLoadingTimeOut: 10000,
                                levelLoadingTimeOut: 10000,
                                fragLoadingMaxRetry: 2,
                                manifestLoadingMaxRetry: 2,
                                levelLoadingMaxRetry: 2,
                                startFragPrefetch: true,
                            });
                            hls.loadSource(src);
                            hls.attachMedia(video);
                            this.hls = hls;
                            // Let the player fire as soon as the first frame is ready.
                            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                                this.buildQualityMenu();
                                this.buildAudioMenu();
                                video.play().catch(() => {});
                            });
                            hls.on(Hls.Events.LEVEL_SWITCHED, (e, data) => {
                                this.quality = data.level;
                            });
                            hls.on(Hls.Events.AUDIO_TRACKS_UPDATED, () => {
                                this.buildAudioMenu();
                            });
                            hls.on(Hls.Events.AUDIO_TRACK_SWITCHED, (e, data) => {
                                this.audioTrack = data.id;
                            });
                            hls.on(Hls.Events.ERROR, (event, data) => {
                                if (!data.fatal) return;
                                console.log('[HLS] Fatal error:', data.type);
                                switch (data.type) {
                                    case Hls.ErrorTypes.NETWORK_ERROR:
                                        // Transient CDN hiccup — resume loading instead of
                                        // switching to the throttled direct stream.
                                        hls.startLoad();
                                        break;
                                    case Hls.ErrorTypes.MEDIA_ERROR:
                                        hls.recoverMediaError();
                                        break;
                                    default:
                                        this.videoError = 'This video could not be loaded. It may still be processing or the file is not available.';
                                        this.isPlaying = false;
                                }
                            });
                        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                            // Native HLS (Safari / iOS)
                            video.src = src;
                            video.load();
                            video.addEventListener('loadedmetadata', () => {
                                this.buildQualityMenu();
                                this.buildAudioMenu();
                            });
                        }
                    },

                    buildQualityMenu() {
                        const hls = this.hls;
                        if (hls && hls.levels && hls.levels.length) {
                            this.levels = hls.levels.map((l, i) => ({
                                index: i,
                                label: (l.height ? l.height + 'p' : (l.name || 'Level ' + (i + 1)))
                            }));
                        } else if (this.$refs.video.levels && this.$refs.video.levels.length) {
                            this.levels = Array.from(this.$refs.video.levels).map((l, i) => ({
                                index: i,
                                label: (l.height ? l.height + 'p' : 'Level ' + (i + 1))
                            }));
                        } else {
                            this.levels = [];
                        }
                    },

                    buildAudioMenu() {
                        const hls = this.hls;
                        if (hls && hls.audioTracks && hls.audioTracks.length) {
                            this.audioTracks = hls.audioTracks.map((t, i) => ({
                                index: i,
                                label: this.prettyAudioName(t.name)
                            }));
                            this.audioTrack = hls.audioTrack;
                        } else if (this.$refs.video.audioTracks && this.$refs.video.audioTracks.length) {
                            this.audioTracks = Array.from(this.$refs.video.audioTracks).map((t, i) => ({
                                index: i,
                                label: this.prettyAudioName(t.language || t.label || ('Audio ' + (i + 1)))
                            }));
                            Array.from(this.$refs.video.audioTracks).forEach((t, i) => {
                                if (t.enabled) this.audioTrack = i;
                            });
                        } else {
                            this.audioTracks = [];
                        }
                    },

                    prettyAudioName(name) {
                        const map = {
                            hin: 'Hindi', eng: 'English', tel: 'Telugu', tam: 'Tamil', kan: 'Kannada',
                            mal: 'Malayalam', ben: 'Bengali', mar: 'Marathi', guj: 'Gujarati',
                            pun: 'Punjabi', 'und': 'Original', spa: 'Spanish', fra: 'French',
                            deu: 'German', jpn: 'Japanese', kor: 'Korean', chi: 'Chinese'
                        };
                        const key = String(name || '').toLowerCase().slice(0, 3);
                        return map[key] || name || 'Audio';
                    },

                    setAudioTrack(index) {
                        this.showSettings = false;
                        this.audioTrack = index;
                        if (this.hls && this.hls.audioTracks && this.hls.audioTracks.length) {
                            this.hls.audioTrack = index;
                        } else if (this.$refs.video.audioTracks) {
                            Array.from(this.$refs.video.audioTracks).forEach((t, i) => { t.enabled = (i === index); });
                        }
                    },

                    activeAudioLabel() {
                        const t = this.audioTracks.find(t => t.index === this.audioTrack);
                        return t ? t.label : '';
                    },

                    setQuality(index) {
                        this.showSettings = false;
                        this.quality = index;
                        if (this.hls && this.hls.levels && this.hls.levels.length) {
                            this.hls.currentLevel = index;
                        } else if (this.$refs.video.levels) {
                            Array.from(this.$refs.video.levels).forEach((l) => { l.enabled = false; });
                            if (index >= 0) this.$refs.video.levels[index].enabled = true;
                        }
                    },

                    toggleSourceQuality(type) {
                        this.showSettings = false;
                        if (type === 'original') {
                            if (this.isDirectStream) return;
                            this.isDirectStream = true;

                            const currentTime = this.$refs.video.currentTime;
                            const wasPlaying = !this.$refs.video.paused;

                            if (this.hls) {
                                this.hls.destroy();
                                this.hls = null;
                            }

                            this.$refs.video.src = "{{ route('video.stream.direct', $video->id) }}";
                            this.$refs.video.load();

                            this.$refs.video.addEventListener('loadedmetadata', () => {
                                this.$refs.video.currentTime = currentTime;
                                if (wasPlaying) this.$refs.video.play();
                            }, { once: true });
                        } else {
                            if (!this.isDirectStream) return;
                            this.isDirectStream = false;

                            const currentTime = this.$refs.video.currentTime;
                            const wasPlaying = !this.$refs.video.paused;

                            this.$refs.video.src = "{{ route('video.stream', $video->id) }}";
                            this.attachHls();

                            this.$refs.video.addEventListener('loadedmetadata', () => {
                                this.$refs.video.currentTime = currentTime;
                                if (wasPlaying) this.$refs.video.play();
                            }, { once: true });
                        }
                    },

                    bindKeyboardShortcuts() {
                        window.addEventListener('keydown', (e) => {
                            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
                            switch (e.key) {
                                case ' ':
                                case 'k':
                                    e.preventDefault();
                                    this.togglePlay();
                                    break;
                                case 'ArrowLeft':
                                    this.skip(-10);
                                    break;
                                case 'ArrowRight':
                                    this.skip(10);
                                    break;
                                case 'ArrowUp':
                                    e.preventDefault();
                                    this.volume = Math.min(1, this.volume + 0.1);
                                    this.updateVolume();
                                    break;
                                case 'ArrowDown':
                                    e.preventDefault();
                                    this.volume = Math.max(0, this.volume - 0.1);
                                    this.updateVolume();
                                    break;
                                case 'f':
                                    this.toggleFullscreen();
                                    break;
                                case 'p':
                                    this.togglePictureInPicture();
                                    break;
                                case 'm':
                                    this.toggleMute();
                                    break;
                                case 'c':
                                    this.toggleCaptions();
                                    break;
                            }
                        });
                    },

                    togglePlay() {
                        this.hasPlayed = true;
                        if (this.$refs.video.paused) {
                            this.$refs.video.play();
                            this.isPlaying = true;
                        } else {
                            this.$refs.video.pause();
                            this.isPlaying = false;
                        }
                    },

                    skip(seconds) {
                        this.$refs.video.currentTime = Math.max(0, Math.min(this.$refs.video.currentTime + seconds, this.duration));
                    },

                    updateProgress() {
                        const vidDuration = this.$refs.video.duration;
                        if (vidDuration && !isNaN(vidDuration) && isFinite(vidDuration) && vidDuration > 0) {
                            this.duration = vidDuration;
                        }

                        if (!this.isDragging && this.duration > 0) {
                            this.currentTime = this.$refs.video.currentTime;
                            this.progress = (this.currentTime / this.duration) * 100;
                        }
                        if (this.isPlaying) {
                            this.watchTimeAccumulated += 1;
                        }
                    },

                    updateVolume() {
                        this.$refs.video.volume = this.volume;
                        this.$refs.video.muted = this.volume === 0;
                        this.isMuted = this.volume === 0;
                    },

                    toggleMute() {
                        if (this.volume > 0) {
                            this.lastVolume = this.volume;
                            this.volume = 0;
                        } else {
                            this.volume = this.lastVolume || 0.7;
                        }
                        this.updateVolume();
                    },

                    startDrag(e) {
                        this.isDragging = true;
                        this.handleDrag(e);
                        
                        const mouseMoveHandler = (e) => this.handleDrag(e);
                        const touchMoveHandler = (e) => {
                            // Prevent scrolling while dragging progress bar on mobile
                            e.preventDefault(); 
                            this.handleDrag(e.touches[0]);
                        };
                        
                        const endDrag = () => {
                            this.isDragging = false;
                            document.removeEventListener('mousemove', mouseMoveHandler);
                            document.removeEventListener('mouseup', endDrag);
                            document.removeEventListener('touchmove', touchMoveHandler);
                            document.removeEventListener('touchend', endDrag);
                            
                            if (this.duration > 0) {
                                this.$refs.video.currentTime = (this.progress / 100) * this.duration;
                            }
                        };
                        
                        document.addEventListener('mousemove', mouseMoveHandler);
                        document.addEventListener('mouseup', endDrag);
                        document.addEventListener('touchmove', touchMoveHandler, { passive: false });
                        document.addEventListener('touchend', endDrag);
                    },

                    handleDrag(e) {
                        if (!this.$refs.progressContainer || !this.duration) return;
                        const rect = this.$refs.progressContainer.getBoundingClientRect();
                        let pos = (e.clientX - rect.left) / rect.width;
                        pos = Math.max(0, Math.min(1, pos));
                        this.progress = pos * 100;
                        this.currentTime = pos * this.duration;
                    },

                    seekTo(seconds) {
                        this.$refs.video.currentTime = Math.max(0, Math.min(seconds, this.duration));
                    },

                    setSpeed(s) {
                        this.speed = s;
                        this.$refs.video.playbackRate = s;
                        this.showSettings = false;
                    },

                    toggleCaptions() {
                        this.captionsOn = !this.captionsOn;
                        this.$refs.video.textTracks.forEach((track) => {
                            track.mode = this.captionsOn ? 'showing' : 'hidden';
                        });
                        this.showSettings = false;
                    },

                    isMobileOrTablet() {
                        return /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
                            || (navigator.maxTouchPoints > 0 && window.matchMedia('(max-width: 1024px)').matches);
                    },

                    rotateToLandscape() {
                        if (!this.isMobileOrTablet()) return;
                        if (screen.orientation && screen.orientation.lock) {
                            screen.orientation.lock('landscape').catch(() => {});
                        }
                    },

                    unrotate() {
                        if (screen.orientation && screen.orientation.unlock) {
                            try { screen.orientation.unlock(); } catch (e) {}
                        }
                    },

                    toggleFullscreen() {
                        const container = this.$refs.videoContainer;
                        if (!container) return;

                        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                            if (this.miniPlayer) this.miniPlayer = false;
                            this.showControls = true;
                            
                            if (container.requestFullscreen) {
                                container.requestFullscreen();
                            } else if (container.webkitRequestFullscreen) {
                                container.webkitRequestFullscreen();
                            }
                        } else {
                            if (document.exitFullscreen) {
                                document.exitFullscreen();
                            } else if (document.webkitExitFullscreen) {
                                document.webkitExitFullscreen();
                            }
                        }
                    },

                    togglePictureInPicture() {
                        if (!this.$refs.video) return;
                        if (document.pictureInPictureElement) {
                            document.exitPictureInPicture();
                        } else if (document.pictureInPictureEnabled) {
                            this.$refs.video.requestPictureInPicture().catch(() => {});
                        }
                    },

                    toggleMiniPlayer() {
                        this.miniPlayer = !this.miniPlayer;
                        if (this.miniPlayer) {
                            const doc = document;
                            if (doc.fullscreenElement || doc.webkitFullscreenElement) {
                                if (doc.exitFullscreen) doc.exitFullscreen();
                                else if (doc.webkitExitFullscreen) doc.webkitExitFullscreen();
                                this.unrotate();
                            }
                            if (document.pictureInPictureElement) {
                                document.exitPictureInPicture();
                            }
                            this.isFullscreen = false;
                            this.showControls = false;
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    },

                    closeMiniPlayer() {
                        this.miniPlayer = false;
                    },

                    skipIntro() {
                        this.seekTo(95);
                    },

                    restart() {
                        this.seekTo(0);
                        this.togglePlay();
                    },

                    syncContinueWatching() {
                        const saved = localStorage.getItem(`krettel-progress-${this.videoId}`);
                        if (saved && this.duration > 0) {
                            const seconds = parseFloat(saved);
                            const pct = (seconds / this.duration) * 100;
                            if (pct > 2 && pct < 95) {
                                this.currentTime = seconds;
                                this.progress = pct;
                            }
                        }
                    },

                    saveProgress() {
                        if (this.duration > 0) {
                            localStorage.setItem(`krettel-progress-${this.videoId}`, this.currentTime);
                        }
                    },

                    formatTime(seconds) {
                        if (!seconds || isNaN(seconds) || !isFinite(seconds)) return '0:00';
                        const m = Math.floor(seconds / 60);
                        const s = Math.floor(seconds % 60);
                        return `${m}:${s < 10 ? '0' : ''}${s}`;
                    },

                    showControlsForAWhile() {
                        this.showControls = true;
                        clearTimeout(this.controlsTimeout);
                        if (this.isPlaying) {
                            this.controlsTimeout = setTimeout(() => {
                                this.showControls = false;
                            }, 2000);
                        }
                    },

                    syncWatchTime() {
                        this.saveProgress();
                        if (this.watchTimeAccumulated > 0) {
                            fetch("{{ route('analytics.watch_time') }}", {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({
                                    video_id: {{ $video->id }},
                                    watch_time: this.watchTimeAccumulated
                                })
                            }).catch(() => {});
                            this.watchTimeAccumulated = 0;
                        }
                    }
                }));
            });
        </script>
        
        <!-- Custom Video Player CSS -->
        <style>
            .video-container {
                position: relative;
                width: 100%;
                background: #000;
            }
            /* Custom Progress Bar */
            .progress-bar-container {
                width: 100%;
                height: 5px;
                background: rgba(255,255,255,0.3);
                cursor: pointer;
                border-radius: 3px;
                position: relative;
                margin-bottom: 15px;
                transition: height 0.2s;
            }
            .progress-bar-container:hover {
                height: 8px;
            }
            .progress-filled {
                height: 100%;
                background: #e50914; /* Netflix Red */
                border-radius: 3px;
                width: 0%;
                position: relative;
            }
            .progress-filled::after {
                content: '';
                position: absolute;
                right: -6px;
                top: 50%;
                transform: translateY(-50%) scale(0);
                width: 12px;
                height: 12px;
                background: #e50914;
                border-radius: 50%;
                transition: transform 0.2s;
            }
            .progress-bar-container:hover .progress-filled::after {
                transform: translateY(-50%) scale(1);
            }
            
            /* Settings Menu */
            .settings-menu {
                position: absolute;
                background: rgba(20, 20, 20, 0.95);
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 8px;
                padding: 10px 0;
                display: none;
                z-index: 50;
                max-height: 60vh;
                overflow-y: auto;
            }
            .settings-menu.show {
                display: block;
            }
            .settings-item {
                padding: 8px 16px;
                color: white;
                cursor: pointer;
                font-size: 14px;
                display: flex;
                justify-content: space-between;
            }
            .settings-item:hover {
                background: rgba(255,255,255,0.1);
            }

            /* Fullscreen: fill the viewport, keep all controls visible */
            .video-container:fullscreen {
                width: 100vw !important;
                height: 100vh !important;
                max-height: none !important;
                aspect-ratio: auto !important;
                background: #000;
                padding: 0;
                border-radius: 0;
            }
            .video-container:-webkit-full-screen {
                width: 100vw !important;
                height: 100vh !important;
                max-height: none !important;
                aspect-ratio: auto !important;
                background: #000;
                padding: 0;
                border-radius: 0;
            }
            .video-container:fullscreen .video-controls {
                border-radius: 0;
            }
            .video-container:-webkit-full-screen .video-controls {
                border-radius: 0;
            }
            .video-container:fullscreen video {
                width: 100% !important;
                height: 100% !important;
            }
            .video-container:-webkit-full-screen video {
                width: 100% !important;
                height: 100% !important;
            }

            /* Volume slider */
            .volume-slider {
                -webkit-appearance: none;
                appearance: none;
                background: rgba(255,255,255,0.25);
                border-radius: 9999px;
                outline: none;
            }
            .volume-slider::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #fff;
                cursor: pointer;
                box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            }
            .volume-slider::-moz-range-thumb {
                width: 12px;
                height: 12px;
                border: none;
                border-radius: 50%;
                background: #fff;
                cursor: pointer;
                box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            }

            /* Custom Red Glow for the main Play button (source.html style) */
            .play-glow {
                box-shadow: 0 0 20px rgba(229, 9, 20, 0.6);
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-zinc-950 text-white selection:bg-primary selection:text-white pb-24 lg:pb-0">
        
        <x-sticky-header />

        <main class="min-h-screen pt-16">
            
            <!-- Custom Video Player Section -->
            @php
                $isProcessing = in_array($video['video_url'] ?? null, ['pending-upload', 'processing']) || $video->hls_status === 'processing';
                $posterUrl = $video->poster ?: $video->thumbnail ?: $video->terabox_image;
                $posterUrl = $posterUrl ? \App\Support\TeraBoxImage::url($posterUrl, 'video', $video->id) : null;
            @endphp
            <div class="transition-all duration-300 ease-in-out"
                 :class="miniPlayer ? 'fixed z-[60] bottom-20 sm:bottom-4 inset-x-0 sm:inset-x-auto sm:right-4 w-full sm:w-96 px-3 sm:px-0' : 'w-full lg:px-4 lg:pt-4'">
            <div class="w-full bg-black flex justify-center items-center relative shadow-2xl video-container"
                 x-ref="videoContainer"
                 :class="{
                    'aspect-video': !isFullscreen,
                    'max-h-none rounded-xl ring-1 ring-zinc-800': miniPlayer && !isFullscreen,
                    'max-h-[85vh] lg:max-w-6xl lg:mx-auto lg:rounded-2xl': !miniPlayer && !isFullscreen,
                    'is-paused': !isPlaying
                 }"
                 :style="isFullscreen ? 'position:fixed;top:0;left:0;width:100vw;height:100vh;max-width:none;max-height:none;z-index:99999;border-radius:0;margin:0;padding:0;overflow:hidden;' : ''"
                 x-data="videoPlayer({{ $video->id }})"
                 x-init="initPlayer()"
                 @mousemove="showControlsForAWhile()"
                 @touchstart="showControlsForAWhile()">

                @if($isProcessing)
                    <!-- Processing Overlay -->
                    <div class="w-full h-full flex flex-col items-center justify-center gap-4">
                        <svg class="w-16 h-16 text-primary animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <p class="text-white font-medium">Uploading to TeraBox...</p>
                        <p class="text-gray-400 text-sm">Your video will appear here once processing is complete.</p>
                    </div>
                @else
                <!-- Native Video Element -->
                <video
                    x-ref="video"
                    class="w-full h-full object-cover cursor-pointer"
                    :style="isFullscreen ? 'width:100%!important;height:100%!important;object-fit:cover!important;' : ''"
                    preload="auto"
                    poster="{{ $posterUrl }}"
                    @click="togglePlay()"
                    @dblclick="toggleFullscreen()"
                    @timeupdate="updateProgress()"
                    @ended="isPlaying = false"
                >
                    <source src="{{ ($video['video_url'] ?? null) === 'terabox-remote' ? route('video.stream', $video->id) : ($streamUrl ?? $video['video_url'] ?? 'https://vjs.zencdn.net/v/oceans.mp4') }}" type="{{ ($video['video_url'] ?? null) === 'terabox-remote' ? 'application/x-mpegURL' : 'video/mp4' }}" />
                    @if(!empty($video->subtitle))
                    <track kind="captions" label="Captions" src="{{ $video->subtitle }}" srclang="en" />
                    @endif
                </video>

                <!-- Buffering Overlay -->
                <div x-show="isBuffering" class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
                    <svg class="w-14 h-14 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </div>

                <!-- Error Overlay -->
                <div x-show="videoError" class="absolute inset-0 flex flex-col items-center justify-center gap-3 z-10 bg-black/70 pointer-events-auto">
                    <svg class="w-14 h-14 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.09 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                    <p class="text-white font-medium text-center px-6" x-text="videoError"></p>
                </div>

                <div class="video-controls absolute inset-0 flex flex-col justify-between p-6 z-20 pointer-events-none rounded-xl bg-gradient-to-t from-black/90 to-transparent transition-opacity duration-300"
                     x-show="!miniPlayer"
                     :class="{ 'opacity-100': !isPlaying || showControls, 'opacity-0': isPlaying && !showControls }">
                    
                    <!-- Top Bar -->
                    <div class="flex justify-between items-center w-full pointer-events-auto px-3 py-2">
                        <button @click.stop="togglePictureInPicture()" class="p-1.5 rounded-full hover:bg-white/10 text-white/90 active:scale-95 transition" :class="{ 'text-primary': isPictureInPicture }" :title="isPictureInPicture ? 'Exit Mini Player' : 'Mini Player'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        
                        <div class="flex items-center space-x-3 relative text-white/90">
                            <!-- CC / Captions Button -->
                            <button @click.stop="toggleCaptions()" class="p-1.5 hover:bg-white/10 rounded-full transition focus:outline-none"
                                    :class="{ 'text-primary': captionsOn }">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V7a2 2 0 012-2zm4 9H5v-2h2v2zm6 0h-2v-2h2v2zm6 0h-2v-2h2v2z"></path></svg>
                            </button>

                            <!-- Picture in Picture Button -->
                            <button @click.stop="togglePictureInPicture()" class="p-1.5 hover:bg-white/10 rounded-full transition focus:outline-none"
                                    :class="{ 'text-primary': isPictureInPicture }">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                            </button>

                            <!-- Restart Button -->
                            <button @click.stop="restart()" class="p-1.5 hover:bg-white/10 rounded-full transition focus:outline-none">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            </button>
                            
                            <!-- Settings Menu Toggle -->
                            <button @click.stop="showSettings = !showSettings" class="p-1.5 hover:bg-white/10 rounded-full transition focus:outline-none">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </button>

                            <!-- Dropdown Settings Menu -->
                            <div class="settings-menu top-full mt-2 right-0 shadow-2xl bg-black/90 backdrop-blur-md rounded-xl border border-gray-700 min-w-[240px] overflow-hidden transition-all transform origin-top-right"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 :class="{ 'show': showSettings }" 
                                 @click.away="showSettings = false">
                                
                                <template x-if="audioTracks.length > 0">
                                    <div>
                                        <div class="px-4 py-2 bg-gray-800/50 border-b border-gray-700 text-gray-400 text-xs font-bold uppercase tracking-wider flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                                            Audio Track
                                            <span class="ml-auto font-medium normal-case text-white" x-text="activeAudioLabel"></span>
                                        </div>
                                        <template x-for="at in audioTracks" :key="at.index">
                                            <div class="settings-item hover:bg-gray-800 transition-colors py-2.5" @click.stop="setAudioTrack(at.index)">
                                                <span class="text-sm" :class="{ 'text-primary font-bold': audioTrack === at.index, 'text-gray-300': audioTrack !== at.index }" x-text="at.label"></span>
                                                <svg x-show="audioTrack === at.index" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <div class="px-4 py-2 bg-gray-800/50 border-y border-gray-700 text-gray-400 text-xs font-bold uppercase tracking-wider flex items-center mt-1">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Playback Speed
                                </div>
                                <template x-for="(s, i) in [0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2]" :key="i">
                                    <div class="settings-item hover:bg-gray-800 transition-colors py-2.5" @click.stop="setSpeed(s)">
                                        <span class="text-sm" :class="{ 'text-primary font-bold': speed === s, 'text-gray-300': speed !== s }" x-text="s === 1 ? 'Normal' : (s + 'x')"></span>
                                        <svg x-show="speed === s" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                </template>

                                <div class="px-4 py-2 bg-gray-800/50 border-y border-gray-700 text-gray-400 text-xs font-bold uppercase tracking-wider flex items-center mt-1">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Quality
                                </div>
                                 @if($video->video_url === 'terabox-remote')
                                     <!-- Quality Modes for TeraBox Remote -->
                                     <div class="settings-item hover:bg-gray-800 transition-colors py-2.5" @click.stop="toggleSourceQuality('auto')">
                                         <span class="text-sm" :class="{ 'text-primary font-bold': !isDirectStream, 'text-gray-300': isDirectStream }">480p (Fast)</span>
                                         <svg x-show="!isDirectStream" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                     </div>
                                     <div class="settings-item hover:bg-gray-800 transition-colors py-2.5" @click.stop="toggleSourceQuality('original')">
                                         <span class="text-sm" :class="{ 'text-primary font-bold': isDirectStream, 'text-gray-300': !isDirectStream }">Original 1080p (Slow)</span>
                                         <svg x-show="isDirectStream" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                     </div>
                                 @else
                                     <div class="settings-item hover:bg-gray-800 transition-colors py-2.5" @click.stop="setQuality(-1)">
                                         <span class="text-sm" :class="{ 'text-primary font-bold': quality === -1, 'text-gray-300': quality !== -1 }">Auto</span>
                                         <svg x-show="quality === -1" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                     </div>
                                     <template x-for="lv in levels" :key="lv.index">
                                         <div class="settings-item hover:bg-gray-800 transition-colors py-2.5" @click.stop="setQuality(lv.index)">
                                             <span class="text-sm" :class="{ 'text-primary font-bold': quality === lv.index, 'text-gray-300': quality !== lv.index }" x-text="lv.label"></span>
                                             <svg x-show="quality === lv.index" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                         </div>
                                     </template>
                                 @endif
                            </div>
                        </div>
                    </div>

                    <!-- Center Controls -->
                    <div class="flex justify-center items-center space-x-6 w-full pointer-events-auto">
                        <!-- Skip Backward 10s -->
                        <button @click.stop="skip(-10)" class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white active:scale-90 transition focus:outline-none">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"></path></svg>
                        </button>

                        <!-- Play/Pause -->
                        <button @click.stop="togglePlay()" class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-red-600/90 border-2 border-red-500 flex items-center justify-center text-white play-glow active:scale-90 transition focus:outline-none">
                            <template x-if="!isPlaying">
                                <svg class="w-7 h-7 sm:w-8 sm:h-8 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </template>
                            <template x-if="isPlaying">
                                <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                            </template>
                        </button>

                        <!-- Skip Forward 10s -->
                        <button @click.stop="skip(10)" class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white active:scale-90 transition focus:outline-none">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.334-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z"></path></svg>
                        </button>
                    </div>
                    <!-- Bottom Bar (Time / Skip Intro / Volume & Fullscreen) -->
                    <div class="flex justify-between items-center w-full pb-4 px-3 pointer-events-auto text-xs">
                        <!-- Left: Timer -->
                        <div class="flex flex-col text-[11px] font-medium leading-tight flex-shrink-0">
                            <span class="text-red-500 font-bold" x-text="formatTime(currentTime)"></span>
                            <span class="text-zinc-400" x-text="formatTime(duration)"></span>
                        </div>

                        <!-- Center: Skip Intro (Dynamic like Netflix/Hotstar, visible only during the opening credits) -->
                        <button x-show="duration > 120 && currentTime >= 5 && currentTime <= 95"
                                @click.stop="skipIntro()" 
                                x-transition
                                class="bg-black/60 backdrop-blur-md border border-zinc-700/80 px-3 py-1.5 rounded-xl flex items-center space-x-1.5 font-semibold text-xs text-zinc-200 active:scale-95 transition flex-shrink-0">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                            <span>Skip Intro</span>
                        </button>

                        <!-- Right: Volume & Fullscreen -->
                        <div class="flex items-center space-x-2 flex-shrink-0">
                            <!-- Volume Control (Expandable on click for mobile/tablet) -->
                            <div class="flex items-center space-x-2 group/volume relative" x-data="{ showVolume: false }" @click.away="showVolume = false">
                                <button @click.stop="if (typeof isMobileOrTablet !== 'undefined' && isMobileOrTablet()) { if (showVolume) { toggleMute() } else { showVolume = true } } else { toggleMute() }" class="p-1 text-zinc-300 hover:text-white transition-colors focus:outline-none">
                                    <template x-if="volume === 0"><svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z m12-2l-4-4m0 4l4-4"></path></svg></template>
                                    <template x-if="volume > 0 && volume <= 0.5"><svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15zM11 5.5L14.5 9M11 18.5L14.5 15m4.036-2.536a3.535 3.535 0 010-5"></path></svg></template>
                                    <template x-if="volume > 0.5"><svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path></svg></template>
                                </button>
                                <input type="range" min="0" max="1" step="0.05" x-model="volume"
                                       @input="updateVolume()" @change="updateVolume()"
                                       :style="`background: linear-gradient(to right, #dc2626 ${volume * 100}%, rgba(255,255,255,0.25) ${volume * 100}%)`"
                                       class="volume-slider h-1 transition-all duration-200 cursor-pointer"
                                       :class="showVolume ? 'w-24 opacity-100' : 'w-0 opacity-0 sm:w-0 sm:opacity-0 sm:group-hover/volume:w-24 sm:group-hover/volume:opacity-100'" />
                            </div>

                            <!-- Fullscreen Toggle -->
                            <button @click.stop="toggleFullscreen()" class="p-1 text-zinc-300 hover:text-white transition-colors focus:outline-none" :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">
                                <svg x-show="!isFullscreen" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                                <svg x-show="isFullscreen" x-cloak class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 4v5H4m16 0h-5V4m0 16v-5h5M4 15h5v5"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar at absolute bottom (with taller h-8 touch target for mobile responsiveness) -->
                <div class="absolute bottom-0 left-0 right-0 h-8 z-30 pointer-events-auto group/progress flex items-end cursor-pointer"
                     @mousedown.prevent="startDrag($event)"
                     @touchstart.prevent="startDrag($event.touches[0])">
                    <div class="progress-bar-container w-full m-0 h-[6px] group-hover/progress:h-2 rounded-none bg-zinc-800" x-ref="progressContainer">
                        <div class="progress-filled bg-red-600 h-full transition-none" :class="{ 'transition-none': isDragging }" :style="`width: ${isNaN(progress) ? 0 : progress}%`"></div>
                    </div>
                </div>
                @endif

                <!-- Mini Player Overlay -->
                <div x-show="miniPlayer" x-cloak
                     class="absolute inset-0 z-40 bg-gradient-to-t from-black/80 via-transparent to-black/40 flex flex-col justify-between p-2 pointer-events-auto">
                    <div class="flex justify-end">
                        <button @click.stop="closeMiniPlayer()" class="p-1.5 rounded-full bg-black/60 text-white hover:bg-black/80 transition" :title="'Close Mini Player'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="flex justify-between items-center">
                        <button @click.stop="togglePlay()" class="p-2 rounded-full bg-black/60 text-white hover:bg-black/80 transition">
                            <template x-if="!isPlaying">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </template>
                            <template x-if="isPlaying">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                            </template>
                        </button>
                        <button @click.stop="toggleMiniPlayer()" class="p-2 rounded-full bg-black/60 text-white hover:bg-black/80 transition" :title="'Expand Player'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
            </div>

            <!-- Video Info Section -->
            <div class="bg-zinc-950">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8 relative z-10">
                    <div class="flex flex-col lg:flex-row gap-8 lg:gap-12">

                        <!-- Left Column: Details -->
                        <div class="flex-1 min-w-0 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-white">{{ $video->title ?? 'Video Title' }}</h1>

                                    <!-- Metadata Bar -->
                                    <div class="flex items-center flex-wrap gap-x-2 gap-y-1 text-xs text-zinc-400 mt-2 font-medium">
                                        <span>{{ $video->release_year ?? '2026' }}</span>
                                        <span>•</span>
                                        <span class="bg-red-600 text-white font-bold px-1.5 py-0.5 rounded text-[10px]">{{ $video->age_rating ?? '18+' }}</span>
                                        <span>•</span>
                                        <span>{{ $video->duration ?? '2h 18m' }}</span>
                                        <span>•</span>
                                        <span class="border border-zinc-700 text-zinc-300 text-[10px] px-1 rounded font-bold">4K HDR</span>
                                        @if($video->views)
                                        <span>•</span>
                                        <span class="text-zinc-500">{{ number_format($video->views) }} views</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Action Items (Watchlist, Share, Download) -->
                                <div class="flex items-center space-x-6 text-zinc-400 pt-1 sm:pt-2 flex-shrink-0 self-start sm:self-auto">
                                    <button x-data="myListToggle({{ $video->id }})" @click="toggle()" class="flex flex-col items-center space-y-1 hover:text-white transition" :class="{ 'text-primary': inList }">
                                        <svg x-show="!inList" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        <svg x-show="inList" x-cloak class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-[10px] font-medium" x-text="inList ? 'In List' : 'Watchlist'"></span>
                                    </button>
                                    <button class="flex flex-col items-center space-y-1 hover:text-white transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                        <span class="text-[10px] font-medium">Share</span>
                                    </button>
                                    <button class="flex flex-col items-center space-y-1 hover:text-white transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"></path></svg>
                                        <span class="text-[10px] font-medium">Download</span>
                                    </button>
                                </div>
                            </div>

                            <p class="text-zinc-400 leading-relaxed text-sm sm:text-base max-w-3xl">
                                {{ $video->description ?? $video->short_description ?? 'An amazing movie description goes here.' }}
                            </p>
                        </div>

                        <!-- Right Column: Cast/Genres -->
                        <div class="w-full lg:w-80 flex-shrink-0 space-y-5 text-sm bg-zinc-900/60 backdrop-blur-md p-6 rounded-2xl border border-zinc-800 shadow-xl">
                            <div class="flex flex-col">
                                <span class="text-zinc-500 font-semibold mb-1">Cast</span> 
                                <span class="text-white leading-relaxed">{{ $video->cast ?? 'Tom Hardy, Charlize Theron, Nicholas Hoult' }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-zinc-500 font-semibold mb-1">Genres</span> 
                                <div class="flex flex-wrap gap-2 mt-1">
                                    @if($video->category)
                                    <a href="{{ route('category.show', $video->category->slug) }}" class="text-white bg-zinc-800 hover:bg-primary transition-colors px-3 py-1 rounded-full text-xs font-medium">{{ $video->category->name }}</a>
                                    @endif
                                    @if($video->tags)
                                        @foreach(explode(',', $video->tags) as $tag)
                                        <a href="{{ route('search.index', ['q' => trim($tag)]) }}" class="text-white bg-zinc-800 hover:bg-primary transition-colors px-3 py-1 rounded-full text-xs font-medium">{{ trim($tag) }}</a>
                                        @endforeach
                                    @else
                                        <a href="#" class="text-white bg-zinc-800 hover:bg-primary transition-colors px-3 py-1 rounded-full text-xs font-medium">Action</a>
                                        <a href="#" class="text-white bg-zinc-800 hover:bg-primary transition-colors px-3 py-1 rounded-full text-xs font-medium">Sci-Fi</a>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-zinc-500 font-semibold mb-1">Director</span> 
                                <span class="text-white">{{ $video->director ?? 'George Miller' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related and Recently Watched Sections -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8 space-y-8">

                @if(isset($recentlyWatched) && $recentlyWatched->count() > 0)
                <!-- Continue Watching (horizontal rail) -->
                <section>
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">Continue Watching</h2>
                        <a href="{{ route('my-list') }}" class="text-xs font-semibold text-red-600 hover:underline">See All</a>
                    </div>

                    <div class="flex space-x-3 overflow-x-auto no-scrollbar py-3">
                        @foreach($recentlyWatched as $recentVideo)
                        @php
                            $rwImg = data_get($recentVideo, 'poster') ?: data_get($recentVideo, 'thumbnail') ?: data_get($recentVideo, 'terabox_image');
                            $rwImg = $rwImg ? \App\Support\TeraBoxImage::url($rwImg, 'video', $recentVideo->id) : 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=300&auto=format&fit=crop';
                        @endphp
                        <a href="{{ route('video.show', $recentVideo->slug) }}" class="min-w-[210px] max-w-[210px] bg-zinc-900/80 border border-zinc-800/80 rounded-2xl p-2 flex items-center space-x-3 relative overflow-hidden group cursor-pointer transition hover:border-zinc-600">
                            <div class="relative w-16 h-16 rounded-xl overflow-hidden flex-shrink-0">
                                <img src="{{ $rwImg }}" alt="{{ $recentVideo->title }}" class="w-full h-full object-cover" loading="eager" fetchpriority="high" decoding="async">
                                <div class="absolute inset-0 bg-black/20 flex items-center justify-center">
                                    <svg class="w-4 h-4 fill-white text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-bold text-white truncate">{{ $recentVideo->title }}</h3>
                                <p class="text-[11px] text-zinc-400 mt-0.5">{{ $recentVideo->duration ?? 'Movie' }}</p>
                                <div class="w-full h-1 bg-zinc-800 rounded-full mt-2 overflow-hidden">
                                    <div class="bg-red-600 h-full w-0"></div>
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </section>
                @endif

                @if(isset($relatedVideos) && $relatedVideos->count() > 0)
                <section>
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">More Like This</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mt-3">
                        @foreach($relatedVideos as $relVideo)
                            <x-video-card :video="$relVideo" />
                        @endforeach
                    </div>
                </section>
                @endif

            </div>
            
        </main>
        
        <!-- Video.js Script -->
        <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

        <x-mobile-nav />
    </body>
</html>
