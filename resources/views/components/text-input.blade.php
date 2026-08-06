@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full px-4 py-3 border border-border bg-secondary text-text focus:border-primary focus:ring-primary focus:ring-1 rounded-xl shadow-sm transition-all duration-300 placeholder-muted']) }}>
