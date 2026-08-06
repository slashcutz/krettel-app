<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center w-full px-6 py-3 bg-gradient-to-r from-primary to-red-600 border border-transparent rounded-full font-bold text-sm text-white tracking-wide hover:scale-[1.02] hover:shadow-[0_4px_20px_-5px_rgba(239,68,68,0.5)] focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background transition-all duration-300']) }}>
    {{ $slot }}
</button>
