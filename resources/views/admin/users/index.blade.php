<x-admin-layout>
    <x-slot name="header">User Management</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-lg font-bold text-white">All Users</h2>
            <a href="{{ route('admin.users.create') }}" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium w-full sm:w-auto text-center">Add User</a>
        </div>
        
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Name</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Email</th>
                        <th scope="col" class="px-6 py-3">Role</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Joined</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4 font-medium text-white flex items-center space-x-3">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=2A2D34&color=fff" class="w-8 h-8 rounded-full">
                                <span>{{ $user->name }}</span>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                @if($user->hasRole('Super Admin'))
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-primary/20 text-primary">Super Admin</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-secondary text-muted">User</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">{{ $user->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="text-primary hover:underline">Edit</a>
                                <form id="user-delete-{{ $user->id }}" action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="confirmDelete('user-delete-{{ $user->id }}', '{{ addslashes($user->name) }}')" class="text-muted hover:text-white">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-muted">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List -->
        <div class="md:hidden divide-y divide-border">
            @foreach($users as $user)
            <div class="p-4">
                <div class="flex items-center gap-3">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=2A2D34&color=fff" class="w-10 h-10 rounded-full flex-shrink-0">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ $user->name }}</p>
                        <p class="text-xs text-muted truncate">{{ $user->email }}</p>
                    </div>
                    @if($user->hasRole('Super Admin'))
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-primary/20 text-primary flex-shrink-0">Super Admin</span>
                    @else
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-secondary text-muted flex-shrink-0">User</span>
                    @endif
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-xs text-muted">Joined {{ $user->created_at->format('M d, Y') }}</span>
                    <div class="flex items-center gap-6">
                        <a href="{{ route('admin.users.edit', $user->id) }}" class="text-sm font-medium text-primary hover:underline">Edit</a>
                        <form id="user-delete-{{ $user->id }}" action="{{ route('admin.users.destroy', $user->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="button" onclick="confirmDelete('user-delete-{{ $user->id }}', '{{ addslashes($user->name) }}')" class="text-sm text-muted hover:text-white">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
            @if($users->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-muted">No users found.</p>
            @endif
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $users->links() }}
        </div>
    </div>
</x-admin-layout>
