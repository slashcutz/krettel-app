<x-admin-layout>
    <x-slot name="header">Create User</x-slot>

    <div class="max-w-4xl bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-lg font-bold text-white">User Details</h2>
        </div>

        <form action="{{ route('admin.users.store') }}" method="POST" class="p-6">
            @csrf

            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-white mb-2">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-2">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-medium text-white mb-2">Password</label>
                        <input type="password" name="password" id="password" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-white mb-2">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    </div>
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-white mb-2">Role</label>
                    <select name="role" id="role" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ old('role', 'User') == $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex justify-end space-x-4">
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 border border-border rounded-lg text-white hover:bg-secondary transition-colors">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors font-medium">Create User</button>
            </div>
        </form>
    </div>
</x-admin-layout>