<x-admin-layout>
    <x-slot name="header">Platform Reports</x-slot>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        <!-- Total Watch Time -->
        <div class="bg-card border border-border rounded-xl p-4 md:p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="text-sm font-semibold text-muted uppercase tracking-wider">Total Watch Time</h3>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($totalWatchTimeHours, 1) }} <span class="text-lg text-muted font-normal">hrs</span></p>
        </div>

        <!-- Device Breakdown -->
        <div class="sm:col-span-2 lg:col-span-3 bg-card border border-border rounded-xl p-4 md:p-6">
            <h3 class="text-sm font-semibold text-muted uppercase tracking-wider mb-4">Views by Device Type</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 md:gap-4">
                @foreach(['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet', 'tv' => 'Smart TV'] as $key => $label)
                    @php
                        $stat = $deviceAnalytics->firstWhere('device_type', $key);
                    @endphp
                    <div class="bg-secondary/50 rounded-lg p-4 text-center border border-border">
                        <div class="text-xs text-muted uppercase">{{ $label }}</div>
                        <div class="text-xl font-bold text-white mt-1">{{ $stat ? number_format($stat->session_count) : 0 }} <span class="text-xs font-normal text-muted">sessions</span></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Top Performing Videos -->
        <div class="bg-card border border-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-bold text-white">Top 10 Performing Videos</h2>
            </div>
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-muted">
                    <thead class="text-xs uppercase bg-secondary/50 text-muted">
                        <tr>
                            <th scope="col" class="px-6 py-3">Video Title</th>
                            <th scope="col" class="px-6 py-3 text-right">Total Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topVideos as $video)
                            <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                                <td class="px-6 py-4 font-medium text-white line-clamp-1">{{ $video->title }}</td>
                                <td class="px-6 py-4 text-right font-bold text-success">{{ number_format($video->views) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-8 text-center text-muted">No data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="md:hidden divide-y divide-border">
                @forelse($topVideos as $video)
                <div class="p-4 flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-white line-clamp-2">{{ $video->title }}</p>
                    <span class="text-sm font-bold text-success flex-shrink-0">{{ number_format($video->views) }}</span>
                </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No data available.</p>
                @endforelse
            </div>
        </div>

        <!-- Latest Signups -->
        <div class="bg-card border border-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-bold text-white">Latest User Signups</h2>
            </div>
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-muted">
                    <thead class="text-xs uppercase bg-secondary/50 text-muted">
                        <tr>
                            <th scope="col" class="px-6 py-3">User</th>
                            <th scope="col" class="px-6 py-3 hidden sm:table-cell">Email</th>
                            <th scope="col" class="px-6 py-3 text-right">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsers as $user)
                            <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                                <td class="px-6 py-4 font-medium text-white">{{ $user->name }}</td>
                                <td class="px-6 py-4 hidden sm:table-cell">{{ $user->email }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">{{ $user->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-muted">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="md:hidden divide-y divide-border">
                @forelse($recentUsers as $user)
                <div class="p-4 flex items-center gap-3">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=2A2D34&color=fff" class="w-9 h-9 rounded-full flex-shrink-0">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ $user->name }}</p>
                        <p class="text-xs text-muted truncate">{{ $user->email }}</p>
                    </div>
                    <span class="text-xs text-muted flex-shrink-0">{{ $user->created_at->diffForHumans() }}</span>
                </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No users found.</p>
                @endforelse
            </div>
        </div>

    </div>
</x-admin-layout>
