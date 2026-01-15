@if(tenant() && empty(tenant()->settings['has_seen_tour']))
    <div x-data="dashboardTour()" x-init="initTour()" class="fixed inset-0 z-[100] pointer-events-none"
        style="display: none;" x-show="step > 0">

        <!-- Backdrop (Dark Overlay) -->
        <div class="absolute inset-0 bg-black/50 transition-opacity duration-500 pointer-events-auto"
            x-transition:enter="opacity-0" x-transition:enter-end="opacity-100" x-show="step > 0"></div>

        <!-- Tooltip Container -->
        <div class="absolute transition-all duration-300 ease-out pointer-events-auto" :style="tooltipStyle">

            <div class="bg-white rounded-2xl shadow-2xl p-6 w-80 relative">
                <!-- Arrow pointing LEFT (tooltip is to the right of element) -->
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -left-2 top-8" x-show="arrowDirection === 'left'">
                </div>
                <!-- Arrow pointing RIGHT (tooltip is to the left of element) -->
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -right-2 top-8"
                    x-show="arrowDirection === 'right'"></div>
                <!-- Arrow pointing UP (tooltip is below element) -->
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -top-2 left-1/2 -translate-x-1/2"
                    x-show="arrowDirection === 'up'"></div>
                <!-- Arrow pointing DOWN (tooltip is above element) -->
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -bottom-2 left-1/2 -translate-x-1/2"
                    x-show="arrowDirection === 'down'"></div>

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
                arrowDirection: 'none',
                tooltipStyle: 'top: 50%; left: 50%; transform: translate(-50%, -50%);',
                highlightedElement: null,
                steps: [
                    {
                        title: "Welcome to IHRAUTO CRM",
                        text: "Your professional workshop management system is ready. Let's take a quick tour.",
                        icon: "👋",
                        target: null,
                        position: 'center'
                    },
                    {
                        title: "Dashboard",
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
                        target: "#nav-work-orders",
                        position: 'right'
                    },
                    {
                        title: "Appointments",
                        text: "Schedule and manage customer appointments efficiently.",
                        icon: "📅",
                        target: "#nav-appointments",
                        position: 'right'
                    },
                    {
                        title: "Finance & Billing",
                        text: "Track payments, invoices, and financial overview of your business.",
                        icon: "💶",
                        target: "#nav-finance",
                        position: 'right'
                    },
                    {
                        title: "You're Ready!",
                        text: "Explore the system and enjoy your new CRM!",
                        icon: "🚀",
                        target: null,
                        position: 'center'
                    }
                ],

                initTour() {
                    // Small delay to ensure DOM is ready
                    setTimeout(() => {
                        this.step = 1;
                        this.updatePosition();
                    }, 500);
                },

                nextStep() {
                    // Clear previous highlight first
                    this.clearHighlight();

                    if (this.step < this.steps.length) {
                        this.step++;
                        // Small delay for smooth transition
                        setTimeout(() => this.updatePosition(), 50);
                    } else {
                        this.finishTour();
                    }
                },

                skipTour() {
                    this.clearHighlight();
                    this.finishTour();
                },

                async finishTour() {
                    this.step = 0;
                    // Save state to backend so it doesn't show again
                    try {
                        await fetch('{{ route("subscription.tour-complete") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        });
                    } catch (e) {
                        console.error('Failed to save tour completion:', e);
                    }
                },

                clearHighlight() {
                    if (this.highlightedElement) {
                        this.highlightedElement.style.zIndex = '';
                        this.highlightedElement.style.position = '';
                        this.highlightedElement.style.boxShadow = '';
                        this.highlightedElement = null;
                    }
                },

                updatePosition() {
                    const currentStep = this.steps[this.step - 1];

                    // Center position for welcome/finish screens
                    if (!currentStep.target || currentStep.position === 'center') {
                        this.tooltipStyle = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                        this.arrowDirection = 'none';
                        return;
                    }

                    const el = document.querySelector(currentStep.target);
                    if (!el) {
                        console.warn('Tour target not found:', currentStep.target);
                        // Fallback to center
                        this.tooltipStyle = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                        this.arrowDirection = 'none';
                        return;
                    }

                    const rect = el.getBoundingClientRect();
                    const tooltipWidth = 320; // w-80 = 20rem = 320px
                    const tooltipHeight = 250; // Approximate height
                    const padding = 16;
                    const scrollY = window.scrollY;

                    let top, left;

                    if (currentStep.position === 'right') {
                        // Tooltip appears to the RIGHT of the element
                        // Arrow points LEFT toward the element
                        top = rect.top + scrollY + (rect.height / 2) - 40;
                        left = rect.right + padding;
                        this.arrowDirection = 'left';
                    } else if (currentStep.position === 'left') {
                        // Tooltip appears to the LEFT of the element
                        // Arrow points RIGHT toward the element
                        top = rect.top + scrollY + (rect.height / 2) - 40;
                        left = rect.left - tooltipWidth - padding;
                        this.arrowDirection = 'right';
                    } else if (currentStep.position === 'bottom') {
                        // Tooltip appears BELOW the element
                        // Arrow points UP toward the element
                        top = rect.bottom + scrollY + padding;
                        left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
                        this.arrowDirection = 'up';
                    } else if (currentStep.position === 'top') {
                        // Tooltip appears ABOVE the element
                        // Arrow points DOWN toward the element
                        top = rect.top + scrollY - tooltipHeight - padding;
                        left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
                        this.arrowDirection = 'down';
                    }

                    // Keep tooltip within viewport
                    if (left < padding) left = padding;
                    if (left + tooltipWidth > window.innerWidth - padding) {
                        left = window.innerWidth - tooltipWidth - padding;
                    }
                    if (top < padding) top = padding;

                    this.tooltipStyle = `top: ${top}px; left: ${left}px;`;

                    // Highlight the target element
                    el.style.zIndex = '101';
                    el.style.position = 'relative';
                    el.style.boxShadow = '0 0 0 4px rgba(255, 255, 255, 0.8), 0 0 0 8px rgba(99, 102, 241, 0.6)';
                    el.style.borderRadius = '8px';
                    this.highlightedElement = el;
                }
            }
        }
    </script>
@endif