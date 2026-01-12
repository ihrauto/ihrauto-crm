@if(tenant() && empty(tenant()->settings['has_seen_tour']))
    <div x-data="dashboardTour()" x-init="initTour()" class="fixed inset-0 z-[100] pointer-events-none"
        style="display: none;" x-show="step > 0">

        <!-- Backdrop (Dark Overlay) -->
        <div class="absolute inset-0 bg-black/50 transition-opacity duration-500 pointer-events-auto"
            x-transition:enter="opacity-0" x-transition:enter-end="opacity-100" x-show="step > 0"></div>

        <!-- Spotlight Element (Hole punch effect via high z-index stacking context or just absolute positioning of tooltip) -->
        <!-- We'll keep it simple: Just tooltips near target elements -->

        <!-- Tooltip Container -->
        <div class="absolute transition-all duration-500 ease-in-out pointer-events-auto" :style="tooltipStyle">

            <div class="bg-white rounded-2xl shadow-2xl p-6 w-80 relative">
                <!-- Arrow -->
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -top-2 left-1/2 -translate-x-1/2"
                    x-show="position === 'bottom'"></div>
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -bottom-2 left-1/2 -translate-x-1/2"
                    x-show="position === 'top'"></div>
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -left-2 top-1/2 -translate-y-1/2"
                    x-show="position === 'right'"></div>
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -right-2 top-1/2 -translate-y-1/2"
                    x-show="position === 'left'"></div>

                <!-- Content -->
                <div class="text-center">
                    <div
                        class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                        <span x-text="steps[step-1].icon"></span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2" x-text="steps[step-1].title"></h3>
                    <p class="text-sm text-gray-500 mb-6" x-text="steps[step-1].text"></p>

                    <div class="flex justify-between items-center">
                        <button @click="skipTour" class="text-xs font-bold text-gray-400 hover:text-gray-600">Skip</button>

                        <button @click="nextStep"
                            class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-200">
                            <span x-text="step === steps.length ? 'Get Started' : 'Next'"></span>
                        </button>
                    </div>

                    <!-- Dots -->
                    <div class="flex justify-center space-x-1 mt-4">
                        <template x-for="i in steps.length">
                            <div class="w-1.5 h-1.5 rounded-full transition-colors"
                                :class="i === step ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function dashboardTour() {
            return {
                step: 0,
                position: 'bottom',
                tooltipStyle: 'top: 50%; left: 50%; transform: translate(-50%, -50%);',
                steps: [
                    {
                        title: "Welcome to IHRAUTO CRM",
                        text: "Your professional workshop management system is ready. Let's take a quick tour.",
                        icon: "👋",
                        target: null
                    },
                    {
                        title: "Dashboard Overview",
                        text: "Track your customers, revenue, and daily operations at a glance.",
                        icon: "📊",
                        target: "#nav-dashboard",
                        position: 'right'
                    },
                    {
                        title: "Vehicle Check-In",
                        text: "Register vehicles and manage active repairs in your workshop.",
                        icon: "🔧",
                        target: "#nav-checkin",
                        position: 'right'
                    },
                    {
                        title: "Tire Hotel",
                        text: "Manage seasonal tire storage, locations, and swap appointments here.",
                        icon: "🛞",
                        target: "#nav-tire-hotel",
                        position: 'right'
                    },
                    {
                        title: "Work Orders",
                        text: "Create detailed work orders with parts, labor, and service tracking.",
                        icon: "📋",
                        target: "a[href*='work-orders']",
                        position: 'right'
                    },
                    {
                        title: "Appointments",
                        text: "Schedule and manage customer appointments efficiently.",
                        icon: "📅",
                        target: "a[href*='appointments']",
                        position: 'right'
                    },
                    {
                        title: "Finance & Billing",
                        text: "Track payments, invoices, and financial overview of your business.",
                        icon: "💶",
                        target: "a[href*='finance']",
                        position: 'right'
                    },
                    {
                        title: "You're Ready!",
                        text: "Explore the system and enjoy your new CRM!",
                        icon: "🚀",
                        target: null
                    }
                ],

                initTour() {
                    // Delay start slightly
                    setTimeout(() => {
                        this.step = 1;
                        this.updatePosition();
                    }, 1000);
                },

                nextStep() {
                    if (this.step < this.steps.length) {
                        this.step++;
                        this.updatePosition();
                    } else {
                        this.finishTour();
                    }
                },

                skipTour() {
                    this.finishTour();
                },

                async finishTour() {
                    this.step = 0;
                    // Save state to backend so it doesn't show again
                    await fetch('{{ route("subscription.tour-complete") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                },

                updatePosition() {
                    const currentStep = this.steps[this.step - 1];

                    if (!currentStep.target) {
                        // Center screen
                        this.tooltipStyle = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                        this.position = 'bottom'; // Default arrow
                        return;
                    }

                    const el = document.querySelector(currentStep.target);
                    if (el) {
                        const rect = el.getBoundingClientRect();
                        const padding = 20;

                        // Simple positioning logic
                        // In a real app, use Popper.js or Floating UI
                        if (currentStep.position === 'right') {
                            this.tooltipStyle = `top: ${rect.top + (rect.height / 2) - 150}px; left: ${rect.right + padding}px;`;
                            this.position = 'right'; // Arrow points right (towards element on left)
                        } else if (currentStep.position === 'left') {
                            this.tooltipStyle = `top: ${rect.top + (rect.height / 2) - 150}px; left: ${rect.left - 320 - padding}px;`;
                            this.position = 'left'; // Arrow points left (towards element on right)
                        } else {
                            // Default bottom
                            this.tooltipStyle = `top: ${rect.bottom + padding}px; left: ${rect.left + (rect.width / 2) - 160}px;`;
                            this.position = 'top'; // Arrow points top
                        }

                        // Highlight effect on element
                        el.style.zIndex = "101";
                        el.style.position = "relative";
                        el.style.boxShadow = "0 0 0 4px rgba(255, 255, 255, 0.5), 0 0 0 8px rgba(99, 102, 241, 0.5)";

                        // Cleanup previous highlights
                        document.querySelectorAll('*').forEach(e => {
                            if (e !== el) {
                                e.style.zIndex = "";
                                e.style.position = "";
                                e.style.boxShadow = "";
                            }
                        });

                    } else {
                        // Fallback if element not found
                        this.tooltipStyle = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                    }
                }
            }
        }
    </script>
@endif