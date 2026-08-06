<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Krettel') }} - Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Global SweetAlert helpers for admin buttons
        function confirmDelete(formId, name = 'this item') {
            Swal.fire({
                title: 'Are you sure?',
                text: `You won't be able to revert this!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e50914',
                cancelButtonColor: '#4B5563',
                confirmButtonText: 'Yes, delete it!',
                background: '#1a1a1a',
                color: '#fff',
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            });
        }
        function notify(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
                background: '#1a1a1a',
                color: '#fff',
            });
            Toast.fire({
                icon: type,
                title: message,
            });
        }
        // Live polling component for the processing-videos bell
        function notificationBell() {
            return {
                open: false,
                pendingCount: 0,
                teraboxExpired: false,
                videos: [],
                pollTimer: null,
                init() {
                    this.refresh();
                    this.pollTimer = setInterval(() => {
                        if (!document.hidden) this.refresh();
                    }, 3000);
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) this.refresh();
                    });
                },
                destroy() {
                    if (this.pollTimer) clearInterval(this.pollTimer);
                },
                statusLabel(s) {
                    return {
                        'pending-upload': 'Pending upload',
                        'processing': 'Processing',
                        'failed': 'Failed',
                        'terabox-remote': 'Complete'
                    }[s] || s;
                },
                async refresh() {
                    try {
                        const res = await fetch('{{ route('admin.pending-notifications') }}', {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.pendingCount = data.pending_count;
                            this.videos = data.videos;
                            if (data.terabox_expired && !this.teraboxExpired) {
                                notify('warning', 'TeraBox cookie expired - update credentials in Settings');
                            }
                            this.teraboxExpired = data.terabox_expired;
                        }
                    } catch (e) { /* ignore polling errors */ }
                }
            }
        }
        // Auto-show flash messages
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => notify('success', {!! json_encode(session('success')) !!}));
        @elseif(session('error'))
            document.addEventListener('DOMContentLoaded', () => notify('error', {!! json_encode(session('error')) !!}));
        @endif
    </script>
</head>
<body class="font-sans antialiased bg-background text-text selection:bg-primary selection:text-white flex h-screen overflow-hidden"
      x-data="{ sidebarOpen: window.innerWidth >= 1024 }" x-cloak>

    <!-- Mobile Overlay -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity class="fixed inset-0 bg-black/60 z-30 lg:hidden" style="display: none;"></div>

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed lg:static inset-y-0 left-0 w-64 bg-secondary/90 backdrop-blur border-r border-border flex flex-col transition-all duration-300 z-40 lg:z-20">
        <div class="h-16 flex items-center justify-between px-6 border-b border-border">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center min-w-0">
                <img src="{{ \App\Support\TeraBoxImage::url(\App\Models\Setting::get('navbar_logo'), 'settings', 'navbar_logo') ?: asset('images/logo.png') }}" alt="{{ config('app.name', 'Krettel') }} logo" loading="eager" fetchpriority="high" class="h-12 lg:h-10 w-auto object-contain">
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden text-muted hover:text-white" aria-label="Close menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="{{ route('upload.index') }}" class="{{ request()->routeIs('upload.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                Upload Video
            </a>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Users & Roles
            </a>
            <a href="{{ route('admin.videos.index') }}" class="{{ request()->routeIs('admin.videos.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path></svg>
                Video Management
            </a>
            <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                Categories
            </a>
            
            <a href="{{ route('admin.banners.index') }}" class="{{ request()->routeIs('admin.banners.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Banners
            </a>
            <a href="{{ route('admin.playlists.index') }}" class="{{ request()->routeIs('admin.playlists.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                Playlists
            </a>
            <a href="{{ route('admin.collections.index') }}" class="{{ request()->routeIs('admin.collections.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Collections
            </a>
            
            <div class="pt-4 mt-4 border-t border-border/50">
                <p class="px-3 text-xs font-semibold text-muted uppercase tracking-wider mb-2">System & Reports</p>
                <a href="{{ route('admin.notifications.index') }}" class="{{ request()->routeIs('admin.notifications.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Notifications
                </a>
                <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Reports
                </a>
                <a href="{{ route('admin.storage.index') }}" class="{{ request()->routeIs('admin.storage.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    Storage
                </a>
                <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'bg-primary text-white' : 'text-muted hover:bg-secondary hover:text-white' }} flex items-center px-3 py-2.5 rounded-lg transition-colors group mt-2">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Settings
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col relative z-10 overflow-hidden bg-background">
        <!-- Top Header -->
        <header class="h-16 bg-background/95 backdrop-blur-md border-b border-border flex items-center justify-between px-4 sm:px-6 z-30">
            <!-- Menu toggle + Breadcrumbs / Title -->
            <div class="flex items-center space-x-3">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-muted hover:text-white" aria-label="Toggle menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="text-white font-medium">
                    {{ $header ?? 'Admin Panel' }}
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="flex items-center space-x-4">
                <div x-data="notificationBell()" class="relative">
                    <button @click="open = !open" class="text-muted hover:text-white transition-colors relative">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span x-show="pendingCount > 0" class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-primary ring-2 ring-background animate-ping"></span>
                    </button>
                    <!-- Notifications Dropdown (full-width sheet on mobile/tablet, card on desktop) -->
                    <div x-show="open" @click.away="open = false" x-transition class="fixed md:absolute top-16 left-2 right-2 md:left-auto md:right-0 md:top-auto md:mt-2 md:w-80 bg-card border border-border rounded-xl shadow-lg py-2 z-50 overflow-hidden" style="display: none;">
                        <!-- TeraBox cookie expiry alert -->
                        <a href="{{ route('admin.settings.index') }}" x-show="teraboxExpired" class="flex items-start px-4 py-3 bg-warning/10 hover:bg-warning/15 transition-colors space-x-3 border-b border-border">
                            <svg class="w-5 h-5 text-warning flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <div class="min-w-0">
                                <p class="text-sm text-warning font-medium">TeraBox cookie expired</p>
                                <p class="text-xs text-muted">Uploads will fail. Update credentials.</p>
                            </div>
                        </a>
                        <div class="px-4 py-2 border-b border-border text-white font-bold text-sm flex items-center justify-between">
                            <span>Processing Videos</span>
                            <span class="text-xs text-muted font-normal"><span x-text="pendingCount"></span> pending</span>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <template x-for="pv in videos" :key="pv.id">
                                <a href="{{ route('admin.videos.index') }}" class="flex items-start px-4 py-3 hover:bg-secondary/50 transition-colors space-x-3">
                                    <svg x-show="pv.status === 'processing' || pv.status === 'pending-upload'" class="w-5 h-5 text-primary animate-spin flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    <svg x-show="pv.status === 'failed'" class="w-5 h-5 text-warning flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-white font-medium truncate" x-text="pv.title"></p>
                                        <p class="text-xs text-muted" x-text="'Uploaded ' + pv.uploaded_human"></p>
                                        <p class="text-xs font-medium" :class="pv.status === 'failed' ? 'text-warning' : 'text-primary'" x-text="statusLabel(pv.status)"></p>
                                        <template x-if="pv.progress !== null && pv.status !== 'failed'">
                                            <div class="mt-1.5 space-y-1">
                                                <div class="w-full bg-secondary rounded-full h-1.5 overflow-hidden">
                                                    <div class="bg-primary h-full rounded-full transition-all duration-500" :style="'width: ' + pv.progress + '%'"></div>
                                                </div>
                                                <p class="text-[11px] text-muted flex justify-between">
                                                    <span x-text="pv.phase === 'pending' ? 'Waiting to start...' : 'Uploading to TeraBox...'"></span>
                                                    <span x-text="pv.uploaded_mb !== null && pv.size_mb !== null ? pv.uploaded_mb + ' MB / ' + pv.size_mb + ' MB' : (pv.size_mb !== null ? pv.size_mb + ' MB' : '')"></span>
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                </a>
                            </template>
                            <p x-show="pendingCount === 0" class="px-4 py-8 text-center text-sm text-muted">No videos currently processing.</p>
                        </div>
                        <div class="px-4 py-2 border-t border-border">
                            <a href="{{ route('admin.videos.index') }}" class="text-xs text-primary hover:underline block text-center w-full">View All Videos</a>
                        </div>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'Admin') }}&background=EF4444&color=fff" alt="Admin" loading="eager" fetchpriority="high" class="w-8 h-8 rounded-full border border-border object-cover">
                        <span class="text-sm font-medium text-white hidden sm:block">{{ Auth::user()->name ?? 'Admin' }}</span>
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <!-- Dropdown Content -->
                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-card border border-border rounded-xl shadow-lg py-1 z-50" style="display: none;">
                        <a href="{{ route('home') }}" class="block px-4 py-2 text-sm text-muted hover:text-white hover:bg-secondary transition-colors">Return to Frontend</a>
                        <div class="border-t border-border my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-primary hover:bg-secondary transition-colors">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 pb-24 lg:pb-6 scrollbar-thin scrollbar-thumb-secondary scrollbar-track-transparent">
            {{ $slot }}
        </main>

        <!-- Mobile/Tablet Bottom Navigation (desktop uses sidebar) -->
        <x-admin-mobile-nav />

        <!-- Footer (desktop only – mobile uses bottom nav) -->
        <footer class="hidden lg:flex px-6 py-4 border-t border-border bg-background/95 flex-shrink-0 items-center justify-between gap-2 text-xs text-muted">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Krettel') }}. All rights reserved.</p>
            <div class="flex items-center space-x-4">
                <a href="{{ route('home') }}" class="hover:text-white transition-colors">Frontend</a>
                <span class="text-border">|</span>
                <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">Dashboard</a>
            </div>
        </footer>
    </div>
</body>
</html>
