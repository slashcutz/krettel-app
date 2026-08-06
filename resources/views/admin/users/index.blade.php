<x-admin-layout>
    <x-slot name="header">User Management</x-slot>

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-lg font-bold text-white">All Users</h2>
            <a href="{{ route('admin.users.create') }}" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium w-full sm:w-auto text-center">Add User</a>
        </div>
        
        <div class="overflow-x-auto">
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
                    @foreach($users as $user)
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
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $users->links() }}
        </div>
    </div>
</x-admin-layout>
