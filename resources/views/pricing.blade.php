@php
    $plans = [
        [
            'key' => 'basic',
            'name' => 'Basic',
            'price' => 'EUR 49',
            'billing' => '/month',
            'tag' => 'Solo workshops',
            'description' => 'For lean teams that need customer intake, work orders, and invoicing in one place.',
            'limits' => ['1 user', '100 customers', '200 vehicles', '50 work orders / month'],
            'features' => [
                'Customer and vehicle records',
                'Check-in workflow',
                'Work orders and technician notes',
                'Appointment calendar',
                'Draft and issued invoices',
            ],
            'featured' => false,
        ],
        [
            'key' => 'standard',
            'name' => 'Standard',
            'price' => 'EUR 149',
            'billing' => '/month',
            'tag' => 'Most popular',
            'description' => 'For growing garages that need shared visibility across service, finance, and storage.',
            'limits' => ['5 users', '1,000 customers', '3,000 vehicles', 'Unlimited work orders'],
            'features' => [
                'Everything in Basic',
                'Tire hotel and storage tracking',
                'Shared bay coordination',
                'Finance dashboard and payments',
                'Management reporting',
            ],
            'featured' => true,
        ],
        [
            'key' => 'custom',
            'name' => 'Custom',
            'price' => 'Custom',
            'billing' => 'plan',
            'tag' => 'Multi-site operators',
            'description' => 'For workshop groups, custom rollout needs, and businesses that need deeper integration support.',
            'limits' => ['Unlimited users', 'Unlimited customers', 'Unlimited vehicles', 'Unlimited work orders'],
            'features' => [
                'Everything in Standard',
                'API access',
                'Custom branding',
                'Custom onboarding support',
                'Dedicated support path',
            ],
            'featured' => false,
        ],
    ];

    $productModules = [
        'Customer and vehicle history',
        'Check-ins, bays, and work orders',
        'Invoices, payments, and finance tracking',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IHRAUTO CRM | Pricing</title>
    <meta name="description" content="Choose the right IHRAUTO CRM package for your workshop and start with a plan-specific signup flow.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .pricing-surface {
            background:
                radial-gradient(circle at top left, rgba(129, 140, 248, 0.3), transparent 24%),
                radial-gradient(circle at top right, rgba(129, 140, 248, 0.16), transparent 24%),
                linear-gradient(180deg, #1e1b4b 0%, #312e81 24%, #4338ca 40%, #eef2ff 40%, #ffffff 66%, #f8fafc 100%);
        }

        .pricing-grid::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 96px 96px;
            mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.28), transparent 82%);
            pointer-events: none;
        }

        .pricing-card {
            transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
        }

        .pricing-card:hover {
            transform: translateY(-6px);
        }
    </style>
</head>

<body class="h-full antialiased text-gray-900">
    <div class="pricing-surface pricing-grid relative min-h-screen overflow-hidden">
        <header class="relative z-10">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center space-x-3">
                    <div
                        class="w-11 h-11 bg-white rounded-xl flex items-center justify-center shadow-lg shadow-indigo-950/20">
                        <svg class="w-6 h-6 text-indigo-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold tracking-tight text-white">IHRAUTO CRM</div>
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-200">Pricing</div>
                    </div>
                </a>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="rounded-lg bg-white px-5 py-2.5 text-sm font-bold text-indigo-950 shadow-md shadow-indigo-950/15 transition hover:bg-indigo-50">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="text-sm font-semibold text-white transition hover:text-indigo-200">
                            Login
                        </a>
                        <a href="{{ route('register', ['plan' => 'standard']) }}"
                            class="rounded-lg bg-white px-5 py-2.5 text-sm font-bold text-indigo-950 shadow-md shadow-indigo-950/15 transition hover:bg-indigo-50">
                            Start Free Trial
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="relative z-10">
            <section class="mx-auto max-w-7xl px-6 pb-20 pt-8 text-center lg:px-8 lg:pt-12">
                <div
                    class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-sm font-semibold text-indigo-100 shadow-sm backdrop-blur">
                    14 days free • No credit card required
                </div>

                <h1 class="mx-auto mt-8 max-w-5xl text-5xl font-extrabold tracking-tight text-white md:text-6xl">
                    Choose the package that fits your workshop.
                </h1>

                <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-indigo-100/80">
                    Minimal setup, clear limits, and a direct button to start the exact plan you want.
                </p>

                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    @foreach ($productModules as $module)
                        <span
                            class="inline-flex items-center rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white shadow-sm ring-1 ring-white/15 backdrop-blur">
                            {{ $module }}
                        </span>
                    @endforeach
                </div>

                <div id="plans" class="mx-auto mt-14 grid max-w-6xl gap-6 lg:grid-cols-3 lg:items-stretch">
                    @foreach ($plans as $plan)
                        <section
                            class="pricing-card overflow-hidden rounded-3xl border {{ $plan['featured'] ? 'border-indigo-300 bg-indigo-950 text-white shadow-2xl shadow-indigo-950/15 lg:-mt-5' : 'border-indigo-100 bg-white text-gray-900 shadow-xl shadow-indigo-200/40' }}">
                            <div class="p-8">
                                <div class="flex items-center justify-between gap-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] {{ $plan['featured'] ? 'bg-indigo-500/20 text-indigo-200 border border-indigo-400/30' : 'bg-indigo-50 text-indigo-700 border border-indigo-100' }}">
                                        {{ $plan['tag'] }}
                                    </span>
                                    @if ($plan['featured'])
                                        <span
                                            class="inline-flex rounded-full bg-accent-500 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">
                                            Popular
                                        </span>
                                    @endif
                                </div>

                                <h2 class="mt-6 text-3xl font-bold">{{ $plan['name'] }}</h2>

                                <div class="mt-4">
                                    <span class="text-5xl font-extrabold">{{ $plan['price'] }}</span>
                                    <span class="ml-1 text-lg font-semibold {{ $plan['featured'] ? 'text-indigo-200' : 'text-indigo-500' }}">
                                        {{ $plan['billing'] }}
                                    </span>
                                </div>

                                <p class="mt-5 min-h-[72px] text-sm leading-7 {{ $plan['featured'] ? 'text-indigo-100/80' : 'text-gray-500' }}">
                                    {{ $plan['description'] }}
                                </p>

                                <div class="mt-6 grid gap-2 text-left">
                                    @foreach ($plan['limits'] as $limit)
                                        <div
                                            class="rounded-2xl px-4 py-3 text-sm font-semibold {{ $plan['featured'] ? 'bg-white/10 text-white' : 'bg-indigo-50 text-indigo-900' }}">
                                            {{ $limit }}
                                        </div>
                                    @endforeach
                                </div>

                                <ul class="mt-6 space-y-3 text-left">
                                    @foreach ($plan['features'] as $feature)
                                        <li class="flex items-start gap-3">
                                            <span
                                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $plan['featured'] ? 'bg-white/15 text-white' : 'bg-indigo-100 text-indigo-700' }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                            </span>
                                            <span class="text-sm leading-7 {{ $plan['featured'] ? 'text-indigo-100' : 'text-gray-600' }}">
                                                {{ $feature }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>

                                <a href="{{ route('register', ['plan' => $plan['key']]) }}"
                                    class="mt-8 inline-flex w-full items-center justify-center rounded-xl px-6 py-3.5 text-sm font-bold uppercase tracking-[0.14em] transition {{ $plan['featured'] ? 'bg-white text-indigo-950 hover:bg-indigo-50' : 'bg-accent-500 text-white hover:bg-accent-600 shadow-md shadow-indigo-200' }}">
                                    Start {{ $plan['name'] }}
                                </a>
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="mx-auto mt-14 max-w-4xl rounded-3xl border border-indigo-100 bg-white/90 px-6 py-6 shadow-lg shadow-indigo-100/50 backdrop-blur">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-indigo-500">Included in the product</p>
                    <p class="mt-3 text-base leading-7 text-gray-600">
                        All plans start with the same core product foundation. The package changes capacity, advanced
                        modules, and rollout depth, not the quality of the workflow.
                    </p>
                </div>
            </section>
        </main>
    </div>
</body>

</html>
