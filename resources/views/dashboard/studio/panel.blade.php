{{-- ENG-009: Dashboard Studio dropdown panel.

    Horizontal grid layout — every available + coming-soon widget visible
    in one view. Wider on desktop (max-w-6xl), full-screen sheet on mobile.
    Each widget cell is a card with a switch; locked widgets show their
    reason badge (Soon / Upgrade / No access) and the switch is disabled.
--}}
<div
    x-show="open"
    x-cloak
    @click.away="close()"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    role="dialog"
    aria-modal="false"
    aria-labelledby="dashboard-studio-title"
    {{-- Mobile: full-viewport overlay sheet.
         Desktop: position relative to the VIEWPORT (not the narrow trigger
         button) so the panel always sits inside the main content area —
         right-anchored, top below the header, left-clamped past the
         sidebar. Width capped so it never overflows past the sidebar. --}}
    class="fixed inset-0 z-50 flex items-start justify-center p-0 sm:inset-auto sm:top-20 sm:right-4 sm:left-auto sm:p-0"
>
    <div class="bg-white shadow-2xl ring-1 ring-black/5 w-full h-full sm:h-auto sm:rounded-2xl sm:w-[min(900px,calc(100vw-var(--sidebar-width,18rem)-2rem))] sm:max-h-[80vh] flex flex-col overflow-hidden">
        {{-- Header. The "working" spinner and error message live HERE
             (inline, fixed slot) instead of as a bottom bar — otherwise
             the panel grows taller while saving and shrinks back when
             done, which reads as a flicker every time the user toggles. --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="min-w-0">
                <h3 id="dashboard-studio-title" class="text-base font-bold text-indigo-950">Dashboard Widgets</h3>
                <div class="text-xs mt-0.5 h-4 flex items-center gap-2">
                    <span class="text-gray-500" x-show="!working && !errorMessage">
                        <span x-text="enabledCount()"></span> on ·
                        <span x-text="unlockedTotal()"></span> available
                    </span>
                    <span x-show="working" x-cloak class="text-indigo-700 font-medium flex items-center gap-1.5">
                        <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Saving…
                    </span>
                    <span x-show="errorMessage" x-cloak class="text-red-700 font-medium truncate" x-text="errorMessage"></span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input
                    id="dashboard-studio-search"
                    type="search"
                    x-model="search"
                    placeholder="Search…"
                    aria-label="Search widgets"
                    class="hidden sm:block text-sm border border-gray-200 rounded-lg px-3 py-1.5 w-48 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
                <button
                    type="button"
                    @click="reset()"
                    :disabled="working"
                    class="text-xs font-medium text-gray-500 hover:text-indigo-700 px-2 py-1 rounded-md hover:bg-gray-50 disabled:opacity-50"
                >Reset</button>
                <button
                    type="button"
                    @click="close()"
                    class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-md"
                    aria-label="Close customizer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile search (hidden on desktop where it lives in the header) --}}
        <div class="px-5 py-3 border-b border-gray-100 sm:hidden">
            <input
                type="search"
                x-model="search"
                placeholder="Search widgets…"
                aria-label="Search widgets"
                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
        </div>

        {{-- Body: categories with horizontal grid of widget cards --}}
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">
            <template x-for="cat in visibleCategories()" :key="cat.key">
                <section>
                    <header class="flex items-baseline justify-between mb-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-indigo-700" x-text="cat.label"></h4>
                        <span class="text-[11px] text-gray-400" x-text="categoryCount(cat.key)"></span>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                        <template x-for="widget in widgetsInCategory(cat.key)" :key="widget.key">
                            <div
                                class="group relative flex items-start justify-between gap-2 px-3 py-2.5 rounded-lg border transition-colors"
                                :class="widget.locked
                                    ? 'border-gray-200 bg-gray-50/60'
                                    : (isEnabled(widget.key)
                                        ? 'border-indigo-200 bg-indigo-50/40'
                                        : 'border-gray-200 bg-white hover:bg-gray-50')"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-sm font-medium text-gray-900 truncate" x-text="widget.label"></p>
                                        <span
                                            x-show="widget.locked"
                                            x-cloak
                                            class="inline-flex items-center text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded"
                                            :class="lockBadgeClass(widget.lock_reason)"
                                            x-text="widget.lock_label"
                                        ></span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="isEnabled(widget.key) ? 'true' : 'false'"
                                    :aria-label="`Toggle ${widget.label}`"
                                    :disabled="widget.locked || working"
                                    @click="toggleWidget(widget.key)"
                                    class="relative inline-flex h-5 w-9 mt-0.5 flex-shrink-0 rounded-full border-2 border-transparent transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500"
                                    :class="isEnabled(widget.key) ? 'bg-indigo-600' : 'bg-gray-300'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition"
                                        :class="isEnabled(widget.key) ? 'translate-x-4' : 'translate-x-0'"
                                    ></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </section>
            </template>

            <div x-show="visibleCategories().length === 0" class="px-5 py-10 text-center text-sm text-gray-500">
                No widgets match your search.
            </div>
        </div>

    </div>
</div>

@once
@push('scripts')
<script nonce="{{ csp_nonce() }}">
    // ENG-009: Dashboard Studio Alpine component.
    document.addEventListener('alpine:init', () => {
        Alpine.data('dashboardStudio', (config) => ({
            open: false,
            search: '',
            working: false,
            errorMessage: '',
            categories: config.categories || {},
            widgets: config.widgets || [],
            enabled: new Set(config.enabled || []),
            storeUrl: config.storeUrl,
            resetUrl: config.resetUrl,
            fragmentUrl: config.fragmentUrl,
            csrf: config.csrf,

            togglePanel() {
                this.open = !this.open;
            },

            close() {
                this.open = false;
            },

            isEnabled(key) {
                return this.enabled.has(key);
            },

            enabledCount() {
                return this.widgets.filter(w => !w.locked && this.enabled.has(w.key)).length;
            },

            unlockedTotal() {
                return this.widgets.filter(w => !w.locked).length;
            },

            visibleCategories() {
                const term = (this.search || '').trim().toLowerCase();
                const used = new Set();
                this.widgets.forEach(w => {
                    if (term && !w.label.toLowerCase().includes(term)) return;
                    used.add(w.category);
                });
                return Object.keys(this.categories)
                    .filter(k => used.has(k))
                    .map(k => ({ key: k, label: this.categories[k] }));
            },

            widgetsInCategory(catKey) {
                const term = (this.search || '').trim().toLowerCase();
                return this.widgets.filter(w => {
                    if (w.category !== catKey) return false;
                    if (term && !w.label.toLowerCase().includes(term)) return false;
                    return true;
                });
            },

            categoryCount(catKey) {
                const list = this.widgets.filter(w => w.category === catKey);
                const unlocked = list.filter(w => !w.locked).length;
                const on = list.filter(w => !w.locked && this.enabled.has(w.key)).length;
                return `${on} on · ${unlocked} available · ${list.length} total`;
            },

            lockBadgeClass(reason) {
                switch (reason) {
                    case 'coming_soon': return 'bg-violet-50 text-violet-700 border border-violet-200';
                    case 'plan': return 'bg-amber-50 text-amber-700 border border-amber-200';
                    case 'permission': return 'bg-gray-100 text-gray-600 border border-gray-200';
                    default: return 'bg-gray-100 text-gray-600 border border-gray-200';
                }
            },

            async toggleWidget(key) {
                // Audit F-1: ignore clicks while a save is in flight.
                // Without this, two quick clicks can race — the second
                // click's optimistic flip and rollback snapshot capture
                // the first click's optimistic state, so a transient
                // server failure can silently revert the user's most
                // recent choice.
                if (this.working) return;

                const widget = this.widgets.find(w => w.key === key);
                if (!widget || widget.locked) return;

                const previousState = new Set(this.enabled);
                if (this.enabled.has(key)) {
                    this.enabled.delete(key);
                } else {
                    this.enabled.add(key);
                }

                await this.persist(previousState);
            },

            async persist(rollbackTo) {
                this.working = true;
                this.errorMessage = '';

                try {
                    const response = await fetch(this.storeUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ keys: [...this.enabled] }),
                    });

                    if (!response.ok) {
                        throw new Error(`Save failed (${response.status})`);
                    }

                    const data = await response.json();
                    this.enabled = new Set(data.enabled || []);
                    await this.swapDashboard();
                } catch (err) {
                    this.enabled = rollbackTo;
                    this.errorMessage = err.message || 'Could not save your changes.';
                } finally {
                    this.working = false;
                }
            },

            async reset() {
                if (!confirm('Reset to default widgets?')) return;

                const previousState = new Set(this.enabled);
                this.working = true;
                this.errorMessage = '';

                try {
                    const response = await fetch(this.resetUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(`Reset failed (${response.status})`);
                    }

                    const data = await response.json();
                    this.enabled = new Set(data.enabled || []);
                    await this.swapDashboard();
                } catch (err) {
                    this.enabled = previousState;
                    this.errorMessage = err.message || 'Could not reset your widgets.';
                } finally {
                    this.working = false;
                }
            },

            // Re-render the dashboard widget grid in place, without
            // closing the panel. If the swap fails for any reason, fall
            // back to a full reload so the user always sees their change.
            async swapDashboard() {
                if (!this.fragmentUrl) {
                    window.location.reload();
                    return;
                }
                try {
                    const response = await fetch(this.fragmentUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) throw new Error(`Fragment load failed (${response.status})`);
                    const html = await response.text();
                    const root = document.getElementById('dashboard-widgets-root');
                    if (!root) throw new Error('Dashboard root not found');
                    root.innerHTML = html;
                    // Re-attach SortableJS to the freshly-rendered grids.
                    if (typeof window.initDashboardSortable === 'function') {
                        window.initDashboardSortable();
                    }
                } catch (err) {
                    // Last-resort fallback: full reload so the user's save
                    // is never silently lost behind a broken UI.
                    window.location.reload();
                }
            },
        }));
    });
</script>
@endpush
@endonce
