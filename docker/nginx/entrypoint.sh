#!/bin/sh
# =============================================================================
# nginx entrypoint — P0 deployment unblock (bug review OPS-01)
# =============================================================================
# The nginx.conf references /etc/nginx/certs/fullchain.pem + privkey.pem. If
# those files don't exist, nginx aborts with a cryptic "cannot load
# certificate" and the container loops-restart, leaving ops to figure out
# what went wrong.
#
# This script detects the missing-cert case BEFORE starting nginx:
#
#   - If REQUIRE_REAL_CERTS=true (production mode), we refuse to start and
#     print a clear error pointing to the docs. No self-signed fallback.
#   - Otherwise we generate a self-signed cert valid for 90 days and print
#     a big scary banner so nobody accidentally runs self-signed in prod.
#
# Replacement with real certs (Let's Encrypt, commercial CA):
#   1. drop fullchain.pem + privkey.pem into the mounted certs volume
#   2. restart the proxy container
# The script detects real certs by the absence of the marker file
# /etc/nginx/certs/.self_signed written by the generation step.
# =============================================================================

set -eu

CERT_DIR="/etc/nginx/certs"
CERT_FILE="$CERT_DIR/fullchain.pem"
KEY_FILE="$CERT_DIR/privkey.pem"
MARKER_FILE="$CERT_DIR/.self_signed"

if [ ! -f "$CERT_FILE" ] || [ ! -f "$KEY_FILE" ]; then
    if [ "${REQUIRE_REAL_CERTS:-false}" = "true" ]; then
        echo "============================================================" >&2
        echo "FATAL: TLS certificates missing and REQUIRE_REAL_CERTS=true" >&2
        echo "" >&2
        echo "Expected files:" >&2
        echo "  $CERT_FILE" >&2
        echo "  $KEY_FILE" >&2
        echo "" >&2
        echo "Provision them before restarting this container." >&2
        echo "See docs/runbook.md for the Let's Encrypt workflow." >&2
        echo "============================================================" >&2
        exit 1
    fi

    echo "============================================================" >&2
    echo "WARNING: generating SELF-SIGNED TLS certificates." >&2
    echo "         These are valid for 90 days and not trusted by any" >&2
    echo "         browser. For production, replace them with real" >&2
    echo "         certs (Let's Encrypt, commercial CA) and set" >&2
    echo "         REQUIRE_REAL_CERTS=true in the proxy environment." >&2
    echo "============================================================" >&2

    mkdir -p "$CERT_DIR"

    # Alpine's nginx image ships libssl but not always the openssl CLI.
    # Install on-demand; this only happens on first boot with no certs.
    if ! command -v openssl >/dev/null 2>&1; then
        echo "Installing openssl for self-signed cert generation..." >&2
        apk add --no-cache openssl >/dev/null 2>&1 || {
            echo "FATAL: could not install openssl; cannot generate certs" >&2
            exit 1
        }
    fi

    openssl req -x509 -nodes -newkey rsa:2048 \
        -days 90 \
        -keyout "$KEY_FILE" \
        -out "$CERT_FILE" \
        -subj "/CN=localhost/O=IHRAUTO CRM self-signed/C=CH" \
        -addext "subjectAltName=DNS:localhost,DNS:*.localhost,IP:127.0.0.1" \
        2>/dev/null

    chmod 644 "$CERT_FILE"
    chmod 600 "$KEY_FILE"
    touch "$MARKER_FILE"

    echo "Self-signed certificates generated at $CERT_DIR" >&2
elif [ -f "$MARKER_FILE" ]; then
    echo "INFO: using self-signed certs (marker file present). Replace" >&2
    echo "      with real certs for production." >&2
else
    echo "INFO: using provided TLS certificates." >&2
fi

# Validate the nginx config up-front so a malformed config produces a
# helpful error instead of silently failing.
nginx -t

exec "$@"
