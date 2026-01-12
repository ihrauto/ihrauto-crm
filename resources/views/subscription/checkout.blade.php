<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IHRAUTO CRM') }} - Checkout</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-50/50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl w-full mx-auto">

            <!-- Back Link -->
            <div class="mb-6 pl-1">
                <a href="{{ route('dev.tenant-switch') }}"
                    class="group inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-500 transition-colors">
                    <div
                        class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mr-2 group-hover:bg-indigo-100 transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                    </div>
                    Back to Plans
                </a>
            </div>

            <div class="grid lg:grid-cols-12 gap-6 items-stretch">

                <!-- LEFTSIDE: Payment Details -->
                <div class="lg:col-span-7 flex flex-col">
                    <div
                        class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden flex-1 flex flex-col">
                        <div class="p-6 sm:p-8 flex-1">
                            <!-- Header -->
                            <div class="flex items-center space-x-3 mb-6">
                                <div
                                    class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-base font-bold shadow-sm ring-1 ring-indigo-100">
                                    1
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900 tracking-tight">Payment Details</h2>
                                    <p class="text-xs text-gray-500 font-medium">Complete your purchase securely</p>
                                </div>
                            </div>

                            <!-- Card Brands -->
                            <div class="flex space-x-2 mb-6">
                                <div
                                    class="h-8 w-12 bg-gray-50 border border-gray-100 rounded flex items-center justify-center grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all cursor-pointer">
                                    <span class="text-[9px] font-extrabold text-blue-900 tracking-tighter">VISA</span>
                                </div>
                                <div
                                    class="h-8 w-12 bg-gray-50 border border-gray-100 rounded flex items-center justify-center grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all cursor-pointer">
                                    <div class="flex -space-x-1">
                                        <div class="w-2.5 h-2.5 rounded-full bg-red-500/80"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-orange-400/80"></div>
                                    </div>
                                </div>
                                <div
                                    class="h-8 w-12 bg-gray-50 border border-gray-100 rounded flex items-center justify-center grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all cursor-pointer">
                                    <span class="text-[9px] font-bold text-blue-400 italic">AMEX</span>
                                </div>
                            </div>

                            <form id="payment-form" onsubmit="processMockPayment(event)" class="flex flex-col">
                                <div class="space-y-4">
                                    <div class="group">
                                        <label
                                            class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Cardholder
                                            Name</label>
                                        <input type="text"
                                            class="w-full bg-gray-50/30 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300"
                                            placeholder="John Doe" required>
                                    </div>

                                    <div class="group">
                                        <label
                                            class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Card
                                            Number</label>
                                        <div class="relative">
                                            <input type="text"
                                                class="w-full bg-gray-50/30 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg pl-9 pr-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300 font-mono"
                                                placeholder="0000 0000 0000 0000" maxlength="19" required>
                                            <div
                                                class="absolute left-0 top-0 bottom-0 pl-3 flex items-center pointer-events-none">
                                                <svg class="h-4 w-4 text-gray-400 group-focus-within:text-indigo-500 transition-colors"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                                                    </path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="group">
                                            <label
                                                class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Expiration</label>
                                            <input type="text"
                                                class="w-full bg-gray-50/30 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300 text-center"
                                                placeholder="MM / YY" maxlength="5" required>
                                        </div>
                                        <div class="group">
                                            <label
                                                class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">CVC
                                                / CVV</label>
                                            <div class="relative">
                                                <input type="text"
                                                    class="w-full bg-gray-50/30 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300 text-center"
                                                    placeholder="123" maxlength="3" required>
                                                <div
                                                    class="absolute right-0 top-0 bottom-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                                    <svg class="h-4 w-4 hover:text-indigo-500 cursor-help transition-colors"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                        </path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-8 grid grid-cols-2 gap-4">
                                    <a href="{{ route('dev.tenant-switch') }}"
                                        class="w-full bg-gray-50 text-gray-700 border border-gray-200 py-3 rounded-xl font-bold text-base hover:bg-gray-100 hover:text-gray-900 active:scale-[0.98] transition-all flex items-center justify-center">
                                        Cancel
                                    </a>
                                    <button type="submit" id="pay-button"
                                        class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold text-base hover:bg-indigo-500 active:scale-[0.98] transition-all shadow-lg shadow-indigo-200 flex items-center justify-center relative overflow-hidden group">
                                        <div
                                            class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                                        </div>
                                        <span class="relative" id="pay-btn-text">Pay {{ $planDetail['price'] }}</span>
                                    </button>
                                </div>

                                <div
                                    class="mt-4 flex items-center justify-center space-x-1.5 text-[10px] text-gray-400">
                                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                        </path>
                                    </svg>
                                    <span>Secure 256-bit SSL Encrypted Payment</span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- RIGHTSIDE: Order Summary -->
                <div class="lg:col-span-5 relative flex flex-col">
                    <!-- Decor Blobs -->
                    <div
                        class="absolute -top-10 -right-10 w-32 h-32 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
                    </div>
                    <div
                        class="absolute -bottom-10 -left-10 w-32 h-32 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000">
                    </div>

                    <div
                        class="bg-slate-900 rounded-2xl shadow-xl overflow-hidden text-white relative z-10 ring-1 ring-white/10 flex-1 flex flex-col">
                        <!-- Card Content -->
                        <div class="p-6 sm:p-8 flex-1">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-6">Order Summary
                            </h3>

                            <div class="mb-8">
                                <h2 class="text-2xl font-bold text-white tracking-tight mb-4">{{ $planDetail['name'] }}
                                    Plan</h2>

                                <!-- Monthly / Yearly Toggle -->
                                <div class="inline-flex bg-slate-800 p-1 rounded-lg mb-2">
                                    <button type="button" onclick="setBilling('monthly')" id="btn-monthly"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-all bg-indigo-500 text-white shadow-sm">
                                        Monthly
                                    </button>
                                    <button type="button" onclick="setBilling('yearly')" id="btn-yearly"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-all text-slate-400 hover:text-white">
                                        Yearly <span class="text-[9px] text-green-400 ml-0.5 font-bold">-20%</span>
                                    </button>
                                </div>
                                <div class="flex items-center text-indigo-300 font-medium text-sm mt-2">
                                    <span id="billing-badge"
                                        class="bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded text-[10px] font-bold mr-2 border border-indigo-500/30">MONTHLY</span>
                                    Subscription
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div
                                    class="flex justify-between items-center text-slate-300 py-2 border-b border-slate-800 text-sm">
                                    <span>Subtotal</span>
                                    <span class="font-mono" id="summary-subtotal">{{ $planDetail['bill'] }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center text-slate-300 py-2 border-b border-slate-800 text-sm">
                                    <span>Tax (8.1%)</span>
                                    <span class="text-emerald-400 font-medium">Included</span>
                                </div>
                                <div class="flex justify-between items-center text-white pt-4">
                                    <span class="text-base font-bold">Total Due</span>
                                    <span class="text-2xl font-bold tracking-tight"
                                        id="summary-total">{{ $planDetail['price'] }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="bg-slate-950/50 p-4 flex items-start space-x-3 text-[10px] text-slate-500 border-t border-slate-800/50 backdrop-blur-sm">
                            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="leading-relaxed">By clicking "Pay", you agree to our Terms of Service. This is a
                                secure
                                mock transaction environment for demonstration purposes.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Pricing Data -->
    <script>
        const pricing = {
            monthly: {
                total: "{{ $planDetail['price'] }}",
                bill: "{{ $planDetail['bill'] }}"
            },
            yearly: {
                total: "{{ $planDetail['price_yearly'] }}",
                bill: "{{ $planDetail['bill_yearly'] }}"
            }
        };

        function setBilling(cycle) {
            const btnMonthly = document.getElementById('btn-monthly');
            const btnYearly = document.getElementById('btn-yearly');
            const badge = document.getElementById('billing-badge');
            const subtotal = document.getElementById('summary-subtotal');
            const total = document.getElementById('summary-total');
            const payBtnText = document.getElementById('pay-btn-text');

            if (cycle === 'monthly') {
                btnMonthly.classList.add('bg-indigo-500', 'text-white', 'shadow-sm');
                btnMonthly.classList.remove('text-slate-400', 'hover:text-white');

                btnYearly.classList.remove('bg-indigo-500', 'text-white', 'shadow-sm');
                btnYearly.classList.add('text-slate-400', 'hover:text-white');

                badge.textContent = 'MONTHLY';
                subtotal.textContent = pricing.monthly.bill;
                total.textContent = pricing.monthly.total;
                payBtnText.textContent = `Pay ${pricing.monthly.total}`;
            } else {
                btnYearly.classList.add('bg-indigo-500', 'text-white', 'shadow-sm');
                btnYearly.classList.remove('text-slate-400', 'hover:text-white');

                btnMonthly.classList.remove('bg-indigo-500', 'text-white', 'shadow-sm');
                btnMonthly.classList.add('text-slate-400', 'hover:text-white');

                badge.textContent = 'YEARLY';
                subtotal.textContent = pricing.yearly.bill;
                total.textContent = pricing.yearly.total;
                payBtnText.textContent = `Pay ${pricing.yearly.total}`;
            }
        }

        async function processMockPayment(e) {
            e.preventDefault();

            const btn = document.getElementById('pay-button');
            const originalText = btn.innerHTML;

            // 1. Loading State
            btn.disabled = true;
            btn.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg> Processing...`;

            // 2. Simulate Delay (Network Request)
            await new Promise(r => setTimeout(r, 1500));

            // 3. Submit to Backend
            try {
                const response = await fetch("{{ route('subscription.process', $tenant->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        plan: "{{ $planDetail['id'] }}"
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Success State
                    btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-500');
                    btn.classList.add('bg-emerald-500', 'hover:bg-emerald-400');
                    btn.innerHTML = `<svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Payment Successful`;

                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 800);
                }
            } catch (error) {
                alert('Payment failed. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>

</html>