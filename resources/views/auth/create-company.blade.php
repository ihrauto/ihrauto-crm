<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        @if($googleUser)
            {{ __('Welcome! You\'re signing up with Google. Please provide your company name to complete registration.') }}
        @else
            {{ __('Welcome! Please create your company to get started.') }}
        @endif
    </div>

    @if($googleUser)
        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                @if(!empty($googleUser['avatar']))
                    <img src="{{ $googleUser['avatar'] }}" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                @endif
                <div>
                    <div class="font-medium text-gray-900">{{ $googleUser['name'] }}</div>
                    <div class="text-sm text-gray-500">{{ $googleUser['email'] }}</div>
                </div>
            </div>
        </div>
    @elseif(isset($user))
        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center mr-3">
                    <span class="text-white font-bold">{{ substr($user->name, 0, 1) }}</span>
                </div>
                <div>
                    <div class="font-medium text-gray-900">{{ $user->name }}</div>
                    <div class="text-sm text-gray-500">{{ $user->email }}</div>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('auth.create-company.store') }}">
        @csrf

        <!-- Company Name -->
        <div>
            <x-input-label for="company_name" :value="__('Company Name')" />
            <x-text-input id="company_name" class="block mt-1 w-full" type="text" name="company_name"
                :value="old('company_name')" required autofocus autocomplete="organization"
                placeholder="Enter your company or business name" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <p class="mt-2 text-xs text-gray-500">
            {{ __('Your 14-day free trial will start immediately.') }}
        </p>

        <div class="flex items-center justify-end mt-6">
            <a href="{{ route('login') }}"
                class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Cancel') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Create Company') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>