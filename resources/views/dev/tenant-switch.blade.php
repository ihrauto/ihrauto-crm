<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IHRAUTO CRM') }} - Pricing</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-slate-50 py-12 px-4 sm:px-6 lg:px-8 flex flex-col justify-center">
        <!-- Header -->
        <div class="text-center max-w-3xl mx-auto mb-10">
            <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold tracking-wide uppercase mb-6 border border-indigo-100">
                <svg class="w-3.5 h-3.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                14-day free trial • No credit card required
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight mb-3">Choose the Perfect Plan</h1>
            
            @if($currentTenant)
                <div class="mt-6 inline-flex items-center bg-white px-4 py-2 rounded-full border border-slate-200 shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span>
                    <span class="text-sm text-slate-700 font-medium">Current: <span class="font-bold border-b border-indigo-100">{{ $currentTenant->name }}</span> 
                        ({{ ucfirst($currentTenant->plan) }})
                        @if($currentTenant->is_trial && $currentTenant->trial_ends_at)
                            <span class="ml-2 text-indigo-600 font-semibold">• Trial: {{ $currentTenant->days_remaining }} days left</span>
                        @endif
                    </span>
                </div>
            @endif
        </div>

        <!-- Messages -->
        <div class="max-w-5xl mx-auto w-full">
            @if(session('success'))
                <div class="bg-emerald-50 text-emerald-800 px-6 py-4 rounded-xl mb-8 flex items-center border border-emerald-100 shadow-sm">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 text-red-800 px-6 py-4 rounded-xl mb-8 flex items-center border border-red-100 shadow-sm">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif
        </div>

        <!-- Pricing Grid -->
        <div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-6 mb-12 items-start">
            @php
                // Define plan configurations
                $plans = [
                    [
                        'id' => 'basic',
                        'name' => 'Basic',
                        'price' => '€49',
                        'period' => '/month',
                        'desc' => 'Perfect for solo mechanics or very small workshops.',
                        'trial' => '14 days free trial',
                        'stats' => [ // New simpler structure for limits
                            ['label' => 'Work Orders', 'value' => '50 /mo'],
                            ['label' => 'Users', 'value' => '1 User'],
                            ['label' => 'Vehicles', 'value' => '200'],
                        ],
                        'features' => [
                            ['name' => 'Dashboard & Analytics', 'included' => true],
                            ['name' => 'Customer Management', 'included' => true],
                            ['name' => 'Vehicle Check-in', 'included' => true],
                            ['name' => 'Appointments', 'included' => true],
                            ['name' => 'Invoicing (Basic)', 'included' => true],
                            ['name' => 'Tire Hotel', 'included' => false],
                            ['name' => 'Multi-user Access', 'included' => false],
                            ['name' => 'Advanced Reports', 'included' => false],
                        ],
                        'popular' => false,
                        'cta' => 'Start Free Trial',
                    ],
                    [
                        'id' => 'standard',
                        'name' => 'Standard',
                        'price' => '€149',
                        'period' => '/month',
                        'desc' => 'Full power for growing garages with multiple employees.',
                        'trial' => '14 days free trial',
                        'stats' => [
                            ['label' => 'Work Orders', 'value' => 'Unlimited'],
                            ['label' => 'Users', 'value' => 'Up to 5'],
                            ['label' => 'Vehicles', 'value' => '3,000'],
                        ],
                        'features' => [
                            ['name' => 'Everything in Basic', 'included' => true, 'bold' => true],
                            ['name' => 'Tire Hotel & Storage', 'included' => true],
                            ['name' => 'Multi-user Access', 'included' => true],
                            ['name' => 'Staff Management', 'included' => true],
                            ['name' => 'Advanced Reports', 'included' => true],
                            ['name' => 'Invoicing (Custom)', 'included' => true],
                            ['name' => 'Email Support', 'included' => true],
                            ['name' => 'API Access', 'included' => false],
                        ],
                        'popular' => true,
                        'cta' => 'Start Free Trial',
                    ],
                    [
                        'id' => 'custom',
                        'name' => 'Custom',
                        'price' => 'Contact',
                        'period' => '',
                        'desc' => 'Tailored solutions for large franchises.',
                        'trial' => null,
                        'stats' => [
                            ['label' => 'Work Orders', 'value' => 'Unlimited'],
                            ['label' => 'Users', 'value' => 'Unlimited'],
                            ['label' => 'Vehicles', 'value' => 'Unlimited'],
                        ],
                        'features' => [
                            ['name' => 'Everything in Standard', 'included' => true, 'bold' => true],
                            ['name' => 'API Access', 'included' => true],
                            ['name' => 'Custom Branding', 'included' => true],
                            ['name' => 'Custom Integrations', 'included' => true],
                            ['name' => 'Data Migration', 'included' => true],
                            ['name' => 'Dedicated Manager', 'included' => true],
                            ['name' => 'On-premise Option', 'included' => true],
                        ],
                        'popular' => false,
                        'cta' => 'Contact Sales',
                    ],
                ];

                $tenantsByPlan = $tenants->keyBy('plan');
            @endphp

            @foreach($plans as $plan)
                @php
                    $isPopular = $plan['popular'];
                    $tenant = $tenantsByPlan[$plan['id']] ?? null;
                    $isActive = $currentTenant && $tenant && $currentTenant->id === $tenant->id;
                @endphp

                <div class="relative group h-full">
                    @if($isPopular)
                        <div class="absolute -top-3 left-0 right-0 flex justify-center z-10">
                            <span class="bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full shadow-md ring-4 ring-white">
                                Most Popular
                            </span>
                        </div>
                    @endif

                    <div class="h-full bg-white rounded-2xl p-5 transition-all duration-300 border {{ $isPopular ? 'border-2 border-indigo-600 shadow-xl scale-105 z-10' : 'border border-gray-100 shadow-sm hover:shadow-lg' }} {{ $isActive ? 'ring-2 ring-emerald-500 ring-offset-2' : '' }} flex flex-col">

                        <!-- Title & Price -->
                        <div class="mb-4 text-center">
                            <h3 class="text-lg font-bold text-slate-900 mb-1">{{ $plan['name'] }}</h3>
                            <div class="flex items-baseline justify-center mb-2">
                                <span class="text-3xl font-extrabold text-slate-900">{{ $plan['price'] }}</span>
                                @if($plan['period'])
                                    <span class="text-slate-500 ml-1 text-sm font-medium">{{ $plan['period'] }}</span>
                                @endif
                            </div>
                            <p class="text-slate-500 text-xs leading-relaxed px-2">{{ $plan['desc'] }}</p>
                        </div>

                        <!-- Key Stats (Replaces the "Limits" grid) -->
                        <div class="flex justify-between items-center bg-slate-50 rounded-xl px-2 py-2 mb-5 border border-slate-100">
                            @foreach($plan['stats'] as $stat)
                                <div class="text-center flex-1 {{ !$loop->last ? 'border-r border-slate-200' : '' }}">
                                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wide mb-0.5">{{ $stat['label'] }}</div>
                                    <div class="text-[11px] font-bold text-indigo-700">{{ $stat['value'] }}</div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Action Button -->
                        <div class="mb-5">
                            @if($isActive)
                                <button disabled
                                    class="w-full py-2 px-4 bg-emerald-50 text-emerald-700 font-bold rounded-lg border border-emerald-100 cursor-default flex items-center justify-center text-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Active Plan
                                </button>
                            @elseif($plan['id'] === 'custom')
                                <a href="mailto:sales@ihrauto.com"
                                    class="block w-full text-center py-2 px-4 rounded-lg font-bold text-sm transition-all duration-200 bg-white border-2 border-slate-200 text-slate-700 hover:border-indigo-600 hover:text-indigo-600">
                                    {{ $plan['cta'] }}
                                </a>
                            @elseif($currentTenant)
                                <a href="{{ route('subscription.checkout', ['tenant' => $currentTenant->id, 'plan' => $plan['id']]) }}"
                                    class="block w-full text-center py-2 px-4 rounded-lg font-bold text-sm transition-all duration-200 {{ $isPopular ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-md hover:shadow-lg' : 'bg-white border-2 border-slate-200 text-slate-700 hover:border-indigo-600 hover:text-indigo-600' }}">
                                    Switch to {{ $plan['name'] }}
                                </a>
                            @else
                                <a href="{{ route('register') }}?plan={{ $plan['id'] }}"
                                    class="block w-full text-center py-2 px-4 rounded-lg font-bold text-sm transition-all duration-200 {{ $isPopular ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-md hover:shadow-lg' : 'bg-white border-2 border-slate-200 text-slate-700 hover:border-indigo-600 hover:text-indigo-600' }}">
                                    {{ $plan['cta'] }}
                                </a>
                            @endif
                        </div>

                        <!-- Divider -->
                        <div class="border-t border-slate-100 mb-4 relative">
                            <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white px-2 text-[9px] font-bold text-slate-300 uppercase tracking-wider">Features</span>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-2 mb-2 flex-1">
                            @foreach($plan['features'] as $feature)
                                <li class="flex items-start">
                                    @if($feature['included'])
                                        <div class="flex-shrink-0 w-3.5 h-3.5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mt-0.5 mr-2">
                                            <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-slate-600 text-xs {{ isset($feature['bold']) ? 'font-bold text-slate-800' : 'font-medium' }}">
                                            {{ $feature['name'] }}
                                        </span>
                                    @else
                                        <div class="flex-shrink-0 w-3.5 h-3.5 rounded-full bg-slate-50 text-slate-300 flex items-center justify-center mt-0.5 mr-2">
                                            <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </div>
                                        <span class="text-slate-400 text-xs">{{ $feature['name'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Footer Actions -->
        <div class="flex justify-center gap-4 text-sm text-slate-400">
            @if($currentTenant)
                <form method="POST" action="{{ route('dev.tenant-clear') }}">
                    @csrf
                    <button type="submit" class="hover:text-red-500 transition-colors duration-200">Reset Selection</button>
                </form>
                <span>&bull;</span>
            @endif
            <a href="{{ route('login') }}" class="hover:text-indigo-600 transition-colors duration-200 font-medium">Continue to Login &rarr;</a>
        </div>
    </div>
</body>
</html>