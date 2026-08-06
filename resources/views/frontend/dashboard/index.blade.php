<x-app-layout>
    <div class="pt-24 pb-12 max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
        <div class="mb-8 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-white">Welcome back, {{ $user->name }}!</h1>
            <a href="{{ route('profile.edit') }}" class="bg-card border border-border text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Profile Settings
            </a>
        </div>

        <div class="space-y-12">
            
            <!-- Continue Watching -->
            <section>
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-primary mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <h2 class="text-2xl font-bold text-white">Continue Watching</h2>
                </div>
                
                @if($watchHistory->count() > 0)
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    @foreach($watchHistory as $history)
                        @if($history->video)
                            <x-video-card :video="$history->video" />
                        @endif
                    @endforeach
                </div>
                @else
                <div class="bg-card/50 border border-border rounded-xl p-8 text-center">
                    <p class="text-muted">You haven't watched any videos recently.</p>
                </div>
                @endif
            </section>

            <!-- My Favorites -->
            <section>
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-primary mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    <h2 class="text-2xl font-bold text-white">My Favorites</h2>
                </div>
                
                @if($favorites->count() > 0)
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    @foreach($favorites as $favorite)
                        @if($favorite->video)
                            <x-video-card :video="$favorite->video" />
                        @endif
                    @endforeach
                </div>
                @else
                <div class="bg-card/50 border border-border rounded-xl p-8 text-center">
                    <p class="text-muted">You haven't added any favorites yet.</p>
                </div>
                @endif
            </section>

            <!-- My Playlists -->
            <section>
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-primary mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h8"></path></svg>
                    <h2 class="text-2xl font-bold text-white">My Playlists</h2>
                </div>
                
                @if($playlists->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($playlists as $playlist)
                    <div class="bg-card border border-border rounded-xl overflow-hidden hover:shadow-2xl transition-all cursor-pointer group">
                        <div class="h-32 bg-gray-800 relative flex items-center justify-center">
                            @if($playlist->thumbnail)
                                <img src="{{ Storage::url($playlist->thumbnail) }}" class="w-full h-full object-cover opacity-60 group-hover:opacity-80 transition-opacity">
                            @else
                                <svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            @endif
                            <div class="absolute right-2 bottom-2 bg-black/80 px-2 py-1 rounded text-xs text-white font-bold">
                                {{ $playlist->items_count ?? 0 }} videos
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-white text-lg truncate">{{ $playlist->name }}</h3>
                            <p class="text-sm text-muted mt-1">{{ $playlist->visibility === 'public' ? 'Public' : 'Private' }} • Updated {{ $playlist->updated_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="bg-card/50 border border-border rounded-xl p-8 text-center">
                    <p class="text-muted">You haven't created any playlists yet.</p>
                </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
