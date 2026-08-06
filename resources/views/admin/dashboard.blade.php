<x-admin-layout>
    <x-slot name="header">Dashboard</x-slot>

    <!-- Stats Widgets -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 md:gap-6 mb-6 md:mb-8">
        <!-- Total Users -->
        <div class="bg-card border border-border rounded-xl p-4 md:p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-10 h-10 md:w-16 md:h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <h3 class="text-xs md:text-sm font-semibold text-muted uppercase tracking-wider">Total Users</h3>
            <p class="text-2xl md:text-3xl font-bold text-white mt-2">{{ number_format($stats['users_count']) }}</p>
        </div>

        <!-- Total Videos -->
        <div class="bg-card border border-border rounded-xl p-4 md:p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-10 h-10 md:w-16 md:h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path></svg>
            </div>
            <h3 class="text-sm font-semibold text-muted uppercase tracking-wider">Total Videos</h3>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($stats['videos_count']) }}</p>
        </div>

        <!-- Total Views -->
        <div class="bg-card border border-border rounded-xl p-4 md:p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-10 h-10 md:w-16 md:h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            </div>
            <h3 class="text-sm font-semibold text-muted uppercase tracking-wider">Total Views</h3>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($stats['views_count']) }}</p>
        </div>

        <!-- Today's Uploads -->
        <div class="bg-card border border-border rounded-xl p-4 md:p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-10 h-10 md:w-16 md:h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            </div>
            <h3 class="text-sm font-semibold text-muted uppercase tracking-wider">Today's Uploads</h3>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($stats['today_uploads']) }}</p>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-muted">videos currently processing</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Videos -->
        <div class="lg:col-span-2 bg-card border border-border rounded-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-border flex justify-between items-center">
                <h3 class="font-bold text-white text-lg">Recently Uploaded</h3>
                <a href="{{ route('admin.videos.index') }}" class="text-sm text-primary hover:underline">View All</a>
            </div>
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-muted">
                    <thead class="text-xs uppercase bg-secondary/50 text-muted">
                        <tr>
                            <th scope="col" class="px-6 py-3">Video</th>
                            <th scope="col" class="px-6 py-3 hidden md:table-cell">Category</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 hidden md:table-cell">Views</th>
                            <th scope="col" class="px-6 py-3 hidden md:table-cell">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentVideos as $video)
                            <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                                <td class="px-6 py-4 flex items-center space-x-3">
                                    <div class="w-12 h-8 bg-secondary rounded overflow-hidden flex-shrink-0">
                                        <img src="{{ $video->thumbnail }}" class="w-full h-full object-cover">
                                    </div>
                                    <div class="font-medium text-white line-clamp-1" title="{{ $video->title }}">
                                        {{ Str::limit($video->title, 30) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell">{{ $video->category->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        @if($video->visibility == 'public') bg-success/20 text-success 
                                        @elseif($video->visibility == 'private') bg-warning/20 text-warning 
                                        @else bg-secondary text-muted @endif">
                                        {{ ucfirst($video->visibility) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell">{{ number_format($video->views) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">{{ $video->created_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-muted">No videos found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="md:hidden divide-y divide-border">
                @forelse($recentVideos as $video)
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-20 h-12 bg-secondary rounded overflow-hidden flex-shrink-0">
                            <img src="{{ $video->thumbnail }}" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white line-clamp-2">{{ $video->title }}</p>
                            <p class="text-xs text-muted mt-1">{{ $video->category->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            @if($video->visibility == 'public') bg-success/20 text-success 
                            @elseif($video->visibility == 'private') bg-warning/20 text-warning 
                            @else bg-secondary text-muted @endif">
                            {{ ucfirst($video->visibility) }}
                        </span>
                        <div class="text-xs text-muted">
                            {{ number_format($video->views) }} views &middot; {{ $video->created_at->format('M d, Y') }}
                        </div>
                    </div>
                </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No videos found.</p>
                @endforelse
            </div>
        </div>
        
        <!-- Activity Log -->
        <div class="bg-card border border-border rounded-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-border">
                <h3 class="font-bold text-white text-lg">System Activity</h3>
            </div>
            <div class="p-6 space-y-6">
                @forelse($activityLogs as $log)
                <div class="flex">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm text-white">{{ $log->description ?? 'Action performed' }}</p>
                        <p class="text-xs text-muted mt-1">{{ $log->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <div class="text-muted text-sm py-4 text-center">
                    No recent activity logs.
                </div>
                @endforelse
            </div>
            <div class="px-6 py-4 border-t border-border bg-secondary/20">
                <a href="{{ route('admin.notifications.index') }}" class="text-sm text-primary hover:underline block text-center w-full">View All Logs</a>
            </div>
        </div>
    </div>
</x-admin-layout>
