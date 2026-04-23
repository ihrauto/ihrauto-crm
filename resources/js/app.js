import "./bootstrap";

import Alpine from "alpinejs";
import Swal from "sweetalert2";

window.Alpine = Alpine;
window.Swal = Swal;

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
