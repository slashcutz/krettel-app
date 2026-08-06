<x-admin-layout>
    <x-slot name="header">Storage Configuration</x-slot>

    <div class="max-w-4xl">
        <div class="bg-card border border-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-bold text-white">Default Storage Provider</h2>
            </div>
            
            <div class="p-6">
                <form action="{{ route('admin.storage.store') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-muted mb-2" for="default_driver">Active Provider</label>
                        <select name="default_driver" id="default_driver" class="w-full bg-secondary border-border text-white rounded-lg focus:ring-primary focus:border-primary">
                            @php $selected = old('default_driver', $storage['default_driver'] ?? 'public'); @endphp
                            @foreach(['local', 'public', 's3', 'terabox'] as $driver)
                                <option value="{{ $driver }}" {{ $selected == $driver ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $driver)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-muted mb-2" for="max_upload_size_mb">Max Upload Size (MB)</label>
                        <input type="number" name="max_upload_size_mb" id="max_upload_size_mb" value="{{ old('max_upload_size_mb', $storage['max_upload_size_mb'] ?? 2048) }}" class="w-full bg-secondary border-border text-white rounded-lg focus:ring-primary focus:border-primary">
                    </div>

                    <div class="border-t border-border pt-6 mt-6">
                        <h3 class="text-white font-medium mb-4">TeraBox (Unofficial Web-API) Credentials</h3>
                        <p class="text-sm text-muted mb-4">Configured via <code>.env</code> (<span class="text-primary">TERABOX_EMAIL</span>, <span class="text-primary">TERABOX_PASSWORD</span> for auto-login, or <span class="text-primary">TERABOX_NDUS</span> cookie for the reliable path). These are auto-loaded and should not be stored in the database.</p>
                        <p class="text-sm text-muted mb-4">To grab the <span class="text-primary">NDUS</span> cookie: log into <a class="text-primary underline" href="https://www.terabox.com" target="_blank">terabox.com</a>, open DevTools (F12) → Application → Cookies → <span class="text-primary">www.terabox.com</span>, and copy the <span class="text-primary">ndus</span> value.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Web Host</label>
                                <input type="text" value="{{ config('terabox.web_host', 'https://www.terabox.com') }}" class="w-full bg-secondary border-border text-white rounded-lg focus:ring-primary focus:border-primary" disabled>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Remote Folder</label>
                                <input type="text" value="{{ config('terabox.remote_dir', '/Apps/Krettel') }}" class="w-full bg-secondary border-border text-white rounded-lg focus:ring-primary focus:border-primary" disabled>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Email</label>
                                <input type="text" value="{{ config('terabox.email') ? 'Configured' : 'Not set' }}" class="w-full bg-secondary border-border text-white rounded-lg focus:ring-primary focus:border-primary" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-primary hover:bg-red-600 text-white px-6 py-2 rounded-lg transition-colors font-bold">Save Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
