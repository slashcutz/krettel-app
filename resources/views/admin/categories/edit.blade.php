<x-admin-layout>
    <x-slot name="header">Edit Category: {{ $category->name }}</x-slot>

    <div class="max-w-4xl bg-card border border-border rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-lg font-bold text-white">Category Details</h2>
        </div>
        
        <form action="{{ route('admin.categories.update', $category->id) }}" method="POST" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-white mb-2">Category Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                </div>
                
                <div>
                    <label for="slug" class="block text-sm font-medium text-white mb-2">Slug</label>
                    <input type="text" name="slug" id="slug" value="{{ old('slug', $category->slug) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" required>
                    <p class="text-xs text-muted mt-1">Unique URL friendly identifier.</p>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-white mb-2">Description</label>
                    <textarea name="description" id="description" rows="4" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">{{ old('description', $category->description) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="icon" class="block text-sm font-medium text-white mb-2">Icon Class</label>
                        <input type="text" name="icon" id="icon" value="{{ old('icon', $category->icon) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" placeholder="fas fa-film">
                    </div>
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-white mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $category->sort_order) }}" class="bg-secondary border border-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>

                <div>
                    <label class="flex items-center space-x-2">
                        <!-- Hidden input to handle unchecked state -->
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-border text-primary focus:ring-primary bg-secondary" {{ $category->is_active ? 'checked' : '' }}>
                        <span class="text-white text-sm">Active (Visible to users)</span>
                    </label>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex justify-end space-x-4">
                <a href="{{ route('admin.categories.index') }}" class="px-4 py-2 border border-border rounded-lg text-white hover:bg-secondary transition-colors">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors font-medium">Save Changes</button>
            </div>
        </form>
    </div>
</x-admin-layout>
