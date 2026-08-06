<x-guest-layout>
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-white mb-2">Reset Password</h2>
        <p class="text-muted text-sm">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link.') }}
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <!-- Email Address -->
        <div>
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="Email Address" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="pt-2">
            <x-primary-button>
                {{ __('Send Reset Link') }}
            </x-primary-button>
        </div>
        
        <div class="text-center mt-4">
            <a href="{{ route('login') }}" class="text-sm text-muted hover:text-white transition-colors duration-200">
                Back to login
            </a>
        </div>
    </form>
</x-guest-layout>
