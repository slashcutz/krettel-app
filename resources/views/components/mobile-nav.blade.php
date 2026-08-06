@php
    $active = 'categories';
    if (request()->routeIs('home')) {
        $active = 'home';
    } elseif (request()->routeIs('search.*')) {
        $active = 'search';
    } elseif (request()->routeIs('category.*')) {
        $active = 'categories';
    } elseif (request()->routeIs('collections.index') || request()->routeIs('collection.*')) {
        $active = 'collections';
    } elseif (request()->routeIs('dashboard') || request()->routeIs('my-list')) {
        $active = 'list';
    }

    $item = fn ($key) => ['active' => $active === $key, 'cls' => $active === $key ? 'netflix-red netflix-glow' : 'text-gray-400 hover:text-white', 'icon' => $active === $key ? 'netflix-red-fill' : ''];
@endphp

<!-- ==================== MOBILE BOTTOM NAVIGATION ==================== -->
<nav class="fixed bottom-4 inset-x-0 z-50 flex justify-center lg:hidden pointer-events-none">
    <div class="glass-nav pointer-events-auto w-full max-w-[380px] mx-4 rounded-3xl px-2 py-3 flex justify-around items-center">
        <a href="{{ route('home') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-semibold {{ $item('home')['cls'] }} transition-all group">
            <i data-lucide="home" class="w-5 h-5 stroke-[2] {{ $item('home')['icon'] }} group-hover:scale-110 transition-transform"></i>
            <span class="tracking-wide">Home</span>
        </a>

        <a href="{{ route('search.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('search')['cls'] }} transition-all group">
            <i data-lucide="search" class="w-5 h-5 stroke-[2] {{ $item('search')['icon'] }} group-hover:scale-110 transition-transform"></i>
            <span class="tracking-wide">Search</span>
        </a>

        <a href="{{ route('search.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('categories')['cls'] }} transition-all group">
            <i data-lucide="layout-grid" class="w-5 h-5 stroke-[2] {{ $item('categories')['icon'] }} group-hover:scale-110 transition-transform"></i>
            <span class="tracking-wide">Categories</span>
        </a>

        <a href="{{ route('collections.index') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('collections')['cls'] }} transition-all group">
            <i data-lucide="layers" class="w-5 h-5 stroke-[2] {{ $item('collections')['icon'] }} group-hover:scale-110 transition-transform"></i>
            <span class="tracking-wide">Collections</span>
        </a>

        <a href="{{ route('my-list') }}" class="flex flex-col items-center space-y-1.5 text-[11px] font-medium {{ $item('list')['cls'] }} transition-all group">
            <i data-lucide="bookmark" class="w-5 h-5 stroke-[2] {{ $item('list')['icon'] }} group-hover:scale-110 transition-transform"></i>
            <span class="tracking-wide">My List</span>
        </a>
    </div>
</nav>

@once
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    (function () {
        function initLucide() {
            if (window.lucide) {
                lucide.createIcons();
            }
        }
        document.addEventListener('DOMContentLoaded', initLucide);
        initLucide();
    })();
</script>
@endonce
