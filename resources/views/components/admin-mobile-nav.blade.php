@php
    $active = '';
    if (request()->routeIs('admin.dashboard')) {
        $active = 'dashboard';
    } elseif (request()->routeIs('upload.*')) {
        $active = 'upload';
    } elseif (request()->routeIs('admin.videos.*')) {
        $active = 'videos';
    } elseif (request()->routeIs('admin.categories.*')) {
        $active = 'categories';
    } elseif (request()->routeIs('admin.users.*')) {
        $active = 'users';
    } elseif (request()->routeIs('admin.settings.*') || request()->routeIs('admin.storage.*')) {
        $active = 'settings';
    }

    $item = fn ($key) => ['active' => $active === $key, 'cls' => $active === $key ? 'text-primary netflix-glow' : 'text-muted hover:text-white'];
@endphp

<!-- ==================== ADMIN MOBILE BOTTOM NAVIGATION ==================== -->
<nav class="fixed bottom-4 inset-x-0 z-50 flex justify-center lg:hidden pointer-events-none">
    <div class="glass-nav pointer-events-auto w-full max-w-[420px] mx-4 rounded-3xl px-2 py-3 flex justify-around items-center">
        <a href="{{ route('admin.dashboard') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-semibold {{ $item('dashboard')['cls'] }} transition-all group">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            <span class="tracking-wide">Home</span>
        </a>

        <a href="{{ route('upload.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('upload')['cls'] }} transition-all group">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
            <span class="tracking-wide">Upload</span>
        </a>

        <a href="{{ route('admin.videos.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('videos')['cls'] }} transition-all group">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path></svg>
            <span class="tracking-wide">Videos</span>
        </a>

        <a href="{{ route('admin.categories.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('categories')['cls'] }} transition-all group">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            <span class="tracking-wide">Categories</span>
        </a>

        <a href="{{ route('admin.users.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('users')['cls'] }} transition-all group">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            <span class="tracking-wide">Users</span>
        </a>

        <a href="{{ route('admin.settings.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('settings')['cls'] }} transition-all group">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            <span class="tracking-wide">Settings</span>
        </a>
    </div>
</nav>
