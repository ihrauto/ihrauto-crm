import "./bootstrap";

import Alpine from "alpinejs";
import Swal from "sweetalert2";
import Sortable from "sortablejs";

window.Alpine = Alpine;
window.Swal = Swal;
window.Sortable = Sortable;

/**
 * ENG-009: Dashboard drag-reorder bootstrap.
 *
 * Attaches SortableJS to every `.dashboard-sortable` grid on the page
 * (small / half / full sections render as separate sortable groups so
 * widgets only swap with peers of the same display size).
 *
 * Idempotent — calling it twice on the same element is a no-op because
 * we mark the container with a flag after the first attach. The Studio
 * panel's in-place fragment swap calls `window.initDashboardSortable()`
 * after replacing the grid HTML so freshly-rendered sortables are wired.
 *
 * On drop we POST the full ordered key list (across all sortables on
 * the page) to /dashboard/studio/reorder. Sending the full list keeps
 * the server-side state authoritative and removes the need to track
 * partial diffs.
 */
window.initDashboardSortable = function () {
    const root = document.getElementById("dashboard-widgets-root");
    if (!root) return;

    const containers = root.querySelectorAll(".dashboard-sortable");
    if (containers.length === 0) return;

    const meta = root.dataset;
    const reorderUrl = meta.reorderUrl;
    const csrfToken = document.querySelector(
        'meta[name="csrf-token"]',
    )?.content;

    const collectOrder = () => {
        const keys = [];
        root.querySelectorAll(".dashboard-widget").forEach((el) => {
            const k = el.dataset.widgetKey;
            if (k) keys.push(k);
        });
        return keys;
    };

    const persistOrder = async () => {
        if (!reorderUrl || !csrfToken) return;
        try {
            const response = await fetch(reorderUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ order: collectOrder() }),
            });
            if (!response.ok) {
                throw new Error(`Reorder failed (${response.status})`);
            }
        } catch (err) {
            window.appLogError && window.appLogError(err);
        }
    };

    containers.forEach((container) => {
        // Audit F-3: explicitly tear down any pre-existing Sortable
        // bound to this container before re-binding. fragment swaps
        // replace innerHTML, which leaves orphaned Sortable instances
        // holding document/window listeners. The dataset.sortableInit
        // flag prevents *double*-init on the SAME element but not the
        // leak from REPLACED elements. Calling Sortable.get covers
        // both: same-element re-init becomes a no-op after destroy().
        const existing = Sortable.get(container);
        if (existing) existing.destroy();

        container.dataset.sortableInit = "1";
        Sortable.create(container, {
            animation: 150,
            ghostClass: "dashboard-widget-ghost",
            chosenClass: "dashboard-widget-chosen",
            dragClass: "dashboard-widget-drag",
            // Same-size class only — don't allow dragging a full-row
            // widget into the small-stat grid (visually broken).
            group: container.dataset.sortableGroup || "default",
            // Audit F-10: on touch devices, give the user 200ms to
            // start scrolling before we hijack the touch as a drag.
            // Without this, every scroll attempt that starts on a
            // widget enters drag mode and the page becomes unscrollable.
            delay: 200,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            onEnd: persistOrder,
        });
    });
};

document.addEventListener("DOMContentLoaded", () => {
    window.initDashboardSortable();
});

/**
 * Centralized client-side error logger.
 *
 * In development: logs to the browser console for debuggability.
 * In production: forwards to Sentry (if loaded) and stays silent in the console.
 *
 * All inline blade <script> blocks should use window.appLogError(...) instead
 * of console.error(...) directly. This keeps production consoles clean while
 * preserving observability via Sentry.
 */
window.appLogError = function (...args) {
    const isProd = document.documentElement.dataset.env === "production";

    // Always try to report to Sentry if it's initialized.
    if (window.Sentry && typeof window.Sentry.captureException === "function") {
        const error = args.find((a) => a instanceof Error);
        if (error) {
            window.Sentry.captureException(error);
        } else if (args.length > 0) {
            window.Sentry.captureMessage(args.map((a) => String(a)).join(" "));
        }
    }

    // In non-production, also dump to console for local debugging.
    if (!isProd && typeof console !== "undefined" && console.error) {
        console.error(...args);
    }
};

Alpine.start();
