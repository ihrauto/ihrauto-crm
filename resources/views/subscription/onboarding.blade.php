<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IHRAUTO CRM') }} - Onboarding</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @keyframes bounce-slow {

            0%,
            100% {
                transform: translateY(-5%);
            }

            50% {
                transform: translateY(5%);
            }
        }

        .animate-bounce-slow {
            animation: bounce-slow 3s infinite ease-in-out;
        }
    </style>
</head>

<body class="font-sans antialiased">
    <div class="fixed inset-0 bg-indigo-950 z-50 flex flex-col items-center justify-center text-white overflow-hidden">
        <!-- Background Effects -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 opacity-30">
            <div class="absolute -top-[30%] -left-[10%] w-[70%] h-[70%] rounded-full bg-indigo-500 blur-3xl filter">
            </div>
            <div class="absolute top-[20%] -right-[10%] w-[60%] h-[60%] rounded-full bg-purple-500 blur-3xl filter">
            </div>
        </div>

        <div class="relative z-10 w-full max-w-2xl px-8 text-center" id="wizard-container">

            <!-- Step 1: Welcome -->
            <div id="step-1" class="step transition-all duration-700 transform translate-y-0 opacity-100">
                <div
                    class="w-24 h-24 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-8 shadow-2xl shadow-emerald-500/50 animate-bounce-slow">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Subscription Active!</h1>
                <p class="text-xl text-indigo-200">Thanks for upgrading to the <span
                        class="text-white font-bold">{{ ucfirst($tenant->plan) }}</span> plan.</p>
            </div>

            <!-- Step 2: Configure Company -->
            <div id="step-2" class="step hidden transition-all duration-700 transform translate-y-8 opacity-0">
                <h2 class="text-3xl font-bold mb-6">Setup Your Garage</h2>

                <form id="setup-form" onsubmit="submitSetup(event)"
                    class="bg-white/10 backdrop-blur-md rounded-2xl p-8 text-left border border-white/20 shadow-xl">
                    @csrf
                    <div class="space-y-4">
                        <!-- Company Name -->
                        <div>
                            <label class="block text-indigo-200 text-sm font-medium mb-1">Garage Name</label>
                            <input type="text" name="company_name" value="{{ $tenant->name }}"
                                class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none"
                                required>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Phone -->
                            <div>
                                <label class="block text-indigo-200 text-sm font-medium mb-1">Phone</label>
                                <input type="text" name="phone" placeholder="+41 ..."
                                    class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none">
                            </div>
                            <!-- Email -->
                            <div>
                                <label class="block text-indigo-200 text-sm font-medium mb-1">Email</label>
                                <input type="email" name="email" value="{{ $tenant->email }}"
                                    class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Address -->
                            <div class="col-span-2">
                                <label class="block text-indigo-200 text-sm font-medium mb-1">Address</label>
                                <input type="text" name="address" placeholder="Street, City..."
                                    class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none">
                            </div>
                            <!-- City -->
                            <div class="hidden">
                                <input type="text" name="city" placeholder="City" value="Prishtine">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Currency -->
                            <div>
                                <label class="block text-indigo-200 text-sm font-medium mb-1">Currency</label>
                                <select name="currency"
                                    class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none">
                                    <option value="EUR">EUR (€)</option>
                                    <option value="CHF">CHF (Fr.)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>
                            <!-- Tax Rate -->
                            <div>
                                <label class="block text-indigo-200 text-sm font-medium mb-1">Default Tax %</label>
                                <input type="number" step="0.1" name="tax_rate" value="8.1"
                                    class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none">
                            </div>
                        </div>

                        <div class="border-t border-indigo-500/30 pt-4 mt-2">
                            <p class="text-xs font-bold text-indigo-300 uppercase tracking-wider mb-3">Bank Details (For
                                Invoices)</p>
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Bank Name -->
                                <div>
                                    <label class="block text-indigo-200 text-sm font-medium mb-1">Bank Name</label>
                                    <input type="text" name="bank_name" placeholder="Raiffeisen Bank"
                                        class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none">
                                </div>
                                <!-- IBAN -->
                                <div>
                                    <label class="block text-indigo-200 text-sm font-medium mb-1">IBAN</label>
                                    <input type="text" name="iban" placeholder="XK05 ..."
                                        class="w-full bg-indigo-950/50 border border-indigo-500/30 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button type="submit" id="save-btn"
                            class="w-full bg-emerald-500 text-white font-bold py-3 rounded-xl hover:bg-emerald-600 transition-colors flex justify-center items-center">
                            Confirm Setup
                        </button>
                        <p class="text-xs text-center text-indigo-300 mt-3">You can change these later in settings.</p>
                    </div>
                </form>
            </div>

            <!-- Step 3: Ready -->
            <div id="step-3" class="step hidden transition-all duration-700 transform translate-y-8 opacity-0">
                <div
                    class="w-24 h-24 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-8 border border-white/20">
                    <span class="text-4xl">🚀</span>
                </div>
                <h1 class="text-4xl font-bold mb-6">You're All Set!</h1>
                <p class="text-lg text-indigo-200 mb-10">Your workshop is ready to perform better than ever.</p>

                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold text-indigo-900 bg-white rounded-xl hover:bg-indigo-50 transition-all transform hover:scale-105 shadow-xl">
                    Go to Dashboard
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7l5 5m0 0l-5 5m5-5H6">
                        </path>
                    </svg>
                </a>
            </div>

        </div>

        <!-- Progress Bar -->
        <div class="absolute bottom-12 w-64 h-1.5 bg-indigo-900/50 rounded-full overflow-hidden">
            <div id="progress-bar" class="h-full bg-emerald-500 w-1/3 transition-all duration-1000 ease-in-out"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const step3 = document.getElementById('step-3');
            const bar = document.getElementById('progress-bar');

            // Sequence
            setTimeout(() => {
                // Transition to Step 2
                fadeOut(step1);
                setTimeout(() => {
                    show(step2);
                    bar.style.width = '50%';
                }, 500);
            }, 2000); // Hold Step 1 for 2s

            window.submitSetup = async function (e) {
                e.preventDefault();
                const btn = document.getElementById('save-btn');
                const originalText = btn.innerHTML;

                // Loading
                btn.innerHTML = 'Saving...';
                btn.disabled = true;

                try {
                    const formData = new FormData(e.target);

                    const response = await fetch("{{ route('subscription.setup') }}", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: formData
                    });

                    if (response.ok) {
                        // Success -> Step 3
                        bar.style.width = '75%';

                        fadeOut(step2);
                        setTimeout(() => {
                            show(step3);
                            bar.style.width = '100%';
                            bar.classList.add('bg-white'); // Flash white
                        }, 500);

                    } else {
                        const err = await response.json();
                        let errorMessage = 'Failed to save settings';
                        
                        if (err.message) {
                            errorMessage = err.message;
                        } else if (err.error) {
                            errorMessage = err.error;
                        } else if (err.errors) {
                            // Validation errors
                            errorMessage = Object.values(err.errors).flat().join('\n');
                        }
                        
                        alert('Error: ' + errorMessage);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                } catch (error) {
                    console.error('Setup error:', error);
                    alert('Connection error: ' + (error.message || 'Please check your internet connection and try again.'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            };

            function fadeOut(el) {
                el.classList.add('opacity-0', '-translate-y-8');
                setTimeout(() => el.classList.add('hidden'), 700);
            }

            function show(el) {
                el.classList.remove('hidden');
                // Trigger reflow
                void el.offsetWidth;
                el.classList.remove('opacity-0', 'translate-y-8');
            }
        });
    </script>
</body>

</html>