<x-admin-layout>
    <x-slot name="header">Category Management</x-slot>

    @if (session('success'))
        <div class="mb-4 bg-success/20 border border-success text-success px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-lg font-bold text-white">All Categories</h2>
            <a href="{{ route('admin.categories.create') }}" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium w-full sm:w-auto text-center">Add Category</a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-muted">
                <thead class="text-xs uppercase bg-secondary/50 text-muted">
                    <tr>
                        <th scope="col" class="px-6 py-3">Name</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Slug</th>
                        <th scope="col" class="px-6 py-3">Status</th>
                        <th scope="col" class="px-6 py-3 hidden md:table-cell">Sort Order</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr class="border-b border-border hover:bg-secondary/30 transition-colors">
                            <td class="px-6 py-4 font-medium text-white flex items-center space-x-3">
                                @if($category->icon)
                                    <i class="{{ $category->icon }} text-primary text-xl"></i>
                                @endif
                                <span>{{ $category->name }}</span>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $category->slug }}</td>
                            <td class="px-6 py-4">
                                @if($category->is_active)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-success/20 text-success">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-secondary text-muted">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">{{ $category->sort_order }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.categories.edit', $category->id) }}" class="text-primary hover:underline">Edit</a>
                                <form action="{{ route('admin.categories.destroy', $category->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-muted hover:text-white" onclick="return confirm('Are you sure you want to delete this category?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-muted">No categories found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-border">
            {{ $categories->links() }}
        </div>
    </div>
</x-admin-layout>
