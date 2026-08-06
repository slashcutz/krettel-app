<x-admin-layout>
    <x-slot name="header">Platform Settings</x-slot>

    <div class="w-full" x-data="{ logoPreview: '{{ \App\Support\TeraBoxImage::url($settings['navbar_logo'] ?? null, 'settings', 'navbar_logo') ?: asset('images/logo.png') }}', colorValue: '{{ old('primary_color', $settings['primary_color'] ?? '#EF4444') }}', showPassword: false }">
        <form action="{{ route('admin.settings.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">

                <!-- General Information -->
                <div class="lg:col-span-2 bg-card border border-border rounded-xl overflow-hidden">
                    <div class="px-5 md:px-6 py-3.5 md:py-4 border-b border-border flex items-center">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <h2 class="text-base md:text-lg font-bold text-white">General Information</h2>
                        </div>
                    </div>

                    <div class="p-5 md:p-6 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="platform_name" class="block text-sm font-medium text-muted mb-1.5">Platform Name</label>
                                <input type="text" id="platform_name" name="platform_name" value="{{ old('platform_name', $settings['platform_name'] ?? config('app.name', 'Krettel')) }}" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="support_email" class="block text-sm font-medium text-muted mb-1.5">Support Email</label>
                                <input type="email" id="support_email" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? env('MAIL_FROM_ADDRESS', 'support@example.com')) }}" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">
                            </div>
                        </div>

                        <div>
                            <label for="seo_description" class="block text-sm font-medium text-muted mb-1.5">SEO Default Meta Description</label>
                            <textarea name="seo_description" id="seo_description" rows="3" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">{{ old('seo_description', $settings['seo_description'] ?? 'Premium video streaming platform.') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Appearance -->
                <div class="bg-card border border-border rounded-xl overflow-hidden">
                    <div class="px-5 md:px-6 py-3.5 md:py-4 border-b border-border flex items-center space-x-3">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <h2 class="text-base md:text-lg font-bold text-white">Appearance</h2>
                    </div>

                    <div class="p-5 md:p-6 space-y-5">
                        <div>
                            <label for="navbar_logo" class="block text-sm font-medium text-muted mb-1.5">Navbar Logo</label>
                            <div class="flex flex-col sm:flex-row sm:items-start items-center space-y-3 sm:space-y-0 sm:space-x-3">
                                <div class="w-28 h-16 bg-secondary border border-border rounded-lg overflow-hidden flex items-center justify-center flex-shrink-0">
                                    <img :src="logoPreview" class="max-w-full max-h-full object-contain" alt="Logo preview">
                                </div>
                                <div class="flex-1 w-full">
                                    <label for="navbar_logo" class="cursor-pointer inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-secondary border border-border rounded-lg hover:border-primary transition-colors">
                                        Choose image
                                    </label>
                                    <input type="file" id="navbar_logo" name="navbar_logo" accept="image/*" class="hidden" @change="const f = $event.target.files[0]; if (f) logoPreview = URL.createObjectURL(f)">
                                    <p class="text-xs text-muted mt-2">PNG, JPG, SVG, WebP. Max 2MB. Replaces the logo in the frontend header.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="primary_color" class="block text-sm font-medium text-muted mb-1.5">Primary Color</label>
                            <div class="flex items-center space-x-2">
                                <input type="color" id="primary_color_picker" name="primary_color" :value="colorValue" @input="colorValue = $event.target.value; document.getElementById('primary_color_text').value = colorValue" class="h-10 w-10 bg-secondary border border-border rounded cursor-pointer flex-shrink-0">
                                <input type="text" id="primary_color_text" x-model="colorValue" @input="document.getElementById('primary_color_picker').value = colorValue" class="w-full min-w-0 bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TeraBox Integration -->
                <div class="lg:col-span-3 bg-card border border-border rounded-xl overflow-hidden">
                    <div class="px-5 md:px-6 py-3.5 md:py-4 border-b border-border flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                            <h2 class="text-base md:text-lg font-bold text-white">TeraBox Integration</h2>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-success/10 text-success">Cloud Storage</span>
                        </div>
                    </div>

                    <div class="p-5 md:p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="terabox_email" class="block text-sm font-medium text-muted mb-1.5">Account Email</label>
                                <input type="email" id="terabox_email" name="terabox_email" value="{{ old('terabox_email', $settings['terabox_email'] ?? config('terabox.email')) }}" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="terabox_password" class="block text-sm font-medium text-muted mb-1.5">Account Password</label>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" id="terabox_password" name="terabox_password" value="{{ old('terabox_password', $settings['terabox_password'] ?? config('terabox.password')) }}" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 pr-10 focus:ring-primary focus:border-primary">
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-white transition-colors" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                        <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="terabox_ndus" class="block text-sm font-medium text-muted mb-1.5">NDUS Session Cookie</label>
                                <input type="text" id="terabox_ndus" name="terabox_ndus" value="{{ old('terabox_ndus', $settings['terabox_ndus'] ?? config('terabox.ndus')) }}" placeholder="Paste NDUS cookie here" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary font-mono text-sm">
                                <p class="text-xs text-muted mt-1.5">When the cookie expires, uploads fail. Paste a fresh <code class="text-white">NDUS</code> value here (or update password below to re-login).</p>
                            </div>
                            <div>
                                <label for="terabox_remote_dir" class="block text-sm font-medium text-muted mb-1.5">Remote Directory</label>
                                <input type="text" id="terabox_remote_dir" name="terabox_remote_dir" value="{{ old('terabox_remote_dir', $settings['terabox_remote_dir'] ?? config('terabox.remote_dir')) }}" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary font-mono text-sm">
                            </div>
                            <div>
                                <label for="terabox_web_host" class="block text-sm font-medium text-muted mb-1.5">Web Host</label>
                                <input type="url" id="terabox_web_host" name="terabox_web_host" value="{{ old('terabox_web_host', $settings['terabox_web_host'] ?? config('terabox.web_host')) }}" class="w-full bg-secondary border border-border text-white rounded-lg px-3 py-2.5 focus:ring-primary focus:border-primary">
                            </div>
                        </div>
                    </div>

                    <div class="px-5 md:px-6 py-4 border-t border-border bg-secondary/20 flex flex-col md:flex-row items-center md:items-center justify-between gap-3">
                        <p class="text-xs text-muted text-center md:text-left">Credentials are stored in the database and override the <code class="text-white">TERABOX_*</code> values in <code class="text-white">.env</code>.</p>
                        <button type="submit" class="w-full md:w-auto md:min-w-[200px] bg-primary hover:bg-red-600 text-white px-8 py-2.5 rounded-lg transition-colors font-bold shadow-[0_0_15px_rgba(239,68,68,0.3)]">Save Settings</button>
                    </div>
                </div>

            </div>
        </form>
    </div>
</x-admin-layout>
