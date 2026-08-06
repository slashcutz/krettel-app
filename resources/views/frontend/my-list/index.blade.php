<x-app-layout>
    <div class="pt-24 pb-12 max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">My List</h1>
            <p class="text-muted mt-2">
                @if($guest)
                    Saved on this {{ $deviceType }} — sign in to sync your list across devices.
                @else
                    Your saved videos, synced across every device you're signed in on.
                @endif
            </p>
        </div>

        <div class="space-y-12">
            <!-- My List -->
            <section>
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-primary mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                    <h2 class="text-2xl font-bold text-white">Saved Videos</h2>
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
                    <p class="text-muted">
                        @if($guest)
                            Your list is empty. Browse the catalog and tap the bookmark on any video to save it to this device.
                        @else
                            You haven't added any videos to your list yet.
                        @endif
                    </p>
                    <a href="{{ route('home') }}" class="inline-block mt-4 text-primary hover:text-white transition-colors font-medium">Browse videos →</a>
                </div>
                @endif
            </section>

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
                    <p class="text-muted">
                        @if($guest)
                            Videos you start watching on this device will show up here.
                        @else
                            You haven't watched any videos recently.
                        @endif
                    </p>
                </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
