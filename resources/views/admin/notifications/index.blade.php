<x-admin-layout>
    <x-slot name="header">System Notifications</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-lg font-bold text-white">Activity Logs & Notifications</h2>
            <form id="clear-notifications" action="{{ route('admin.notifications.clear-all') }}" method="POST" class="w-full sm:w-auto">
                @csrf
                <button type="button" onclick="confirmDelete('clear-notifications', 'all notifications')" class="bg-secondary hover:bg-secondary/80 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium border border-border w-full sm:w-auto">Clear All</button>
            </form>
        </div>
        
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Event Description</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">User/Source</th>
                        <th scope="col" class="px-6 py-3">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4 font-medium text-white flex items-center">
                                <svg class="w-4 h-4 text-primary mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $notification->description }}
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $notification->user->name ?? 'System' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $notification->created_at->format('M d, Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-muted">No system notifications found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List -->
        <div class="md:hidden divide-y divide-border">
            @forelse($notifications as $notification)
            <div class="p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white">{{ $notification->description }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="text-xs text-muted">{{ $notification->user->name ?? 'System' }}</span>
                            <span class="text-xs text-muted">&middot;</span>
                            <span class="text-xs text-muted">{{ $notification->created_at->format('M d, Y H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">No system notifications found.</p>
            @endforelse
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $notifications->links() }}
        </div>
    </div>
</x-admin-layout>
