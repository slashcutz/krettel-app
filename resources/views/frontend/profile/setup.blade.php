<x-guest-layout>
    <div class="text-center mb-8">
        <h2 class="text-3xl font-bold text-white mb-2">Set up your profile</h2>
        <p class="text-muted">Personalize your streaming experience</p>
    </div>

    <form method="POST" action="{{ route('profile.setup.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Avatar Upload -->
        <div class="flex flex-col items-center mb-6">
            <div class="relative w-24 h-24 mb-4 rounded-full bg-secondary border-2 border-border overflow-hidden flex items-center justify-center group cursor-pointer hover:border-primary transition-colors duration-300">
                <span class="text-muted group-hover:text-primary transition-colors">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                </span>
                <input type="file" name="avatar" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*">
            </div>
            <label class="text-sm text-muted">Upload Avatar</label>
        </div>

        <!-- Username -->
        <div>
            <x-text-input id="username" class="block w-full" type="text" name="username" :value="old('username')" required placeholder="Choose a Username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Language -->
        <div>
            <select name="language" class="w-full px-4 py-3 border border-border bg-secondary text-text focus:border-primary focus:ring-primary focus:ring-1 rounded-xl shadow-sm transition-all duration-300">
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
            </select>
        </div>

        <!-- Theme -->
        <div>
            <select name="theme" class="w-full px-4 py-3 border border-border bg-secondary text-text focus:border-primary focus:ring-primary focus:ring-1 rounded-xl shadow-sm transition-all duration-300">
                <option value="dark">Dark Theme</option>
                <option value="light">Light Theme</option>
                <option value="system">System Default</option>
            </select>
        </div>

        <div class="pt-4">
            <x-primary-button>
                {{ __('Complete Setup') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
