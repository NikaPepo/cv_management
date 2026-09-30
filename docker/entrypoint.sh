#!/bin/bash
# ============================================================
# Production container entrypoint.
#
# Runs as the only process under tini (PID 1) so SIGTERM from
# Render is forwarded to both PHP-FPM and nginx, letting the
# container shut down cleanly.
#
# Failure policy:
#   - PostgreSQL not reachable -> retry forever (orchestrator
#     restart loop will keep retrying). The container itself
#     does NOT start.
#   - Doctrine migrations fail  -> ABORT container start with
#     non-zero exit. Never bring up an application against an
#     unknown / partially-migrated schema.
#   - Cache clear / warm fail    -> WARN, continue (cache can
#     be regenerated on first request).
#   - Admin bootstrap fail       -> WARN, continue (admin can
#     be created later via Render shell).
# ============================================================
set -u

cd /var/www

PORT="${PORT:-8080}"

# ---- Replace __PORT__ placeholder in nginx config ----
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/http.d/default.conf

# ---- Startup diagnostic: print trusted_hosts / trusted_proxies ----
# Temporary diagnostic for the "Untrusted Host" incident on Render.
# Prints only the host-validation env vars (no secrets). Fails the
# container start if SYMFONY_TRUSTED_HOSTS is unset, empty, or does
# not contain the expected Render hostname — that's the failure mode
# behind "Symfony\Component\HttpKernel\Exception\BadRequestHttpException:
# Untrusted Host".
EXPECTED_HOST="cv-management-1ccc.onrender.com"
echo "[entrypoint] === trusted_hosts / trusted_proxies diagnostic ==="
if [[ -n "${SYMFONY_TRUSTED_HOSTS:-}" ]]; then
    echo "[entrypoint] SYMFONY_TRUSTED_HOSTS is set: yes"
    echo "[entrypoint] SYMFONY_TRUSTED_HOSTS value: '${SYMFONY_TRUSTED_HOSTS}'"
    echo "[entrypoint] SYMFONY_TRUSTED_HOSTS length: ${#SYMFONY_TRUSTED_HOSTS}"
    # Normalize before substring match. Trusted_hosts accepts either:
    #   - literal:        "cv-management-1.onrender.com"
    #   - escaped regex:  "^cv-management-1\.onrender\.com$"
    # Strip ^, $, and backslashes so both forms contain the hostname
    # after normalization.
    NORMALIZED="${SYMFONY_TRUSTED_HOSTS//\\/}"
    NORMALIZED="${NORMALIZED//^/}"
    NORMALIZED="${NORMALIZED//$/}"
    if [[ "${NORMALIZED}" != *"${EXPECTED_HOST}"* ]]; then
        echo "[entrypoint] FATAL: SYMFONY_TRUSTED_HOSTS does not contain expected hostname '${EXPECTED_HOST}'."
        echo "[entrypoint] Expected to find '${EXPECTED_HOST}' (after stripping regex anchors / backslashes) in SYMFONY_TRUSTED_HOSTS."
        echo "[entrypoint] Normalized value was: '${NORMALIZED}'"
        echo "[entrypoint] Check Render Dashboard → Environment → SYMFONY_TRUSTED_HOSTS for typos / old value / hidden whitespace."
        echo "[entrypoint] (Allowed forms: 'cv-management-1.onrender.com' literal, or '^(.*\\\\.)?cv-management-1\\\\.onrender\\\\.com$' anchored regex.)"
        exit 1
    fi
    echo "[entrypoint] SYMFONY_TRUSTED_HOSTS contains expected hostname '${EXPECTED_HOST}': OK"
else
    echo "[entrypoint] SYMFONY_TRUSTED_HOSTS is set: no"
    echo "[entrypoint] FATAL: SYMFONY_TRUSTED_HOSTS is not set in the container environment."
    echo "[entrypoint] Without SYMFONY_TRUSTED_HOSTS, Symfony's framework.trusted_hosts is empty"
    echo "[entrypoint] and every request to Symfony returns 400 'Untrusted Host'."
    echo "[entrypoint] Set SYMFONY_TRUSTED_HOSTS in Render Dashboard → Environment"
    echo "[entrypoint] (allowed: 'cv-management-1.onrender.com' or anchored regex). Then redeploy."
    exit 1
fi
if [[ -n "${SYMFONY_TRUSTED_PROXIES:-}" ]]; then
    echo "[entrypoint] SYMFONY_TRUSTED_PROXIES is set: yes"
    echo "[entrypoint] SYMFONY_TRUSTED_PROXIES value: '${SYMFONY_TRUSTED_PROXIES}'"
else
    echo "[entrypoint] SYMFONY_TRUSTED_PROXIES is set: no (Symfony will trust no proxies)"
fi
echo "[entrypoint] === end diagnostic ==="

# ---- Wait for PostgreSQL ----
# Block startup until DB is reachable. Loop with backoff; we
# intentionally never give up here because Render's restart
# policy handles the eventual success case.
if [[ "${DATABASE_URL:-}" =~ @([^:/]+):([0-9]+) ]]; then
    DB_HOST="${BASH_REMATCH[1]}"
    DB_PORT="${BASH_REMATCH[2]}"
    echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
    until pg_isready -h "$DB_HOST" -p "$DB_PORT" -q 2>/dev/null; do
        echo "[entrypoint]   not ready, retrying in 2s..."
        sleep 2
    done
    echo "[entrypoint] PostgreSQL is ready."
else
    echo "[entrypoint] DATABASE_URL not set or unparseable; skipping pg_isready wait."
fi

# ---- Run Doctrine migrations ----
# doctrine:migrations:migrate is idempotent: Doctrine records every
# applied migration FQCN in migration_versions and skips it on the
# next run. --allow-no-migration exits 0 when there is nothing to do
# (subsequent restarts).
#
# Failure of this step is FATAL. We must never serve requests against
# a schema of unknown provenance. Render will mark the deploy as
# failed and we can fix forward instead of running an inconsistent app.
echo "[entrypoint] Running Doctrine migrations..."
if ! php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
    echo "[entrypoint] FATAL: Doctrine migrations failed."
    echo "[entrypoint] Refusing to start application against an unknown schema."
    echo "[entrypoint] Check Render logs / DATABASE_URL / migration files."
    exit 1
fi
echo "[entrypoint] Doctrine migrations OK."

# ---- Clear + warm Symfony cache ----
# Run as www-data so cache files end up owned correctly. Failures
# here are non-fatal — Symfony will regenerate cache lazily on the
# first request — but we still log so operators can investigate.
echo "[entrypoint] Warming Symfony cache..."
php bin/console cache:clear --no-interaction --env=prod \
    || echo "[entrypoint] WARNING: cache:clear returned non-zero; continuing."
php bin/console cache:warm --no-interaction --env=prod \
    || echo "[entrypoint] WARNING: cache:warm returned non-zero; continuing."

# ---- Bootstrap admin from env vars (one-shot, optional) ----
# Only runs if BOTH ADMIN_BOOTSTRAP_EMAIL and ADMIN_BOOTSTRAP_PASSWORD
# are set. Both are optional; missing either means "skip bootstrap".
# Idempotent: app:create-admin re-promotes ROLE_ADMIN if user exists.
#
# We deliberately do NOT echo the password, and we unset both vars
# after the call so they don't remain in the process environment
# longer than necessary. After this point, ADMIN_BOOTSTRAP_*
# env vars can be safely deleted from the Render dashboard.
if [[ -n "${ADMIN_BOOTSTRAP_EMAIL:-}" && -n "${ADMIN_BOOTSTRAP_PASSWORD:-}" ]]; then
    echo "[entrypoint] Bootstrapping admin from env vars..."
    if php bin/console app:create-admin \
            "$ADMIN_BOOTSTRAP_EMAIL" \
            "$ADMIN_BOOTSTRAP_PASSWORD" \
            --no-interaction; then
        echo "[entrypoint] Admin bootstrap OK."
    else
        echo "[entrypoint] WARNING: Admin bootstrap returned non-zero; continuing."
    fi
    unset ADMIN_BOOTSTRAP_EMAIL ADMIN_BOOTSTRAP_PASSWORD
else
    echo "[entrypoint] ADMIN_BOOTSTRAP_EMAIL / ADMIN_BOOTSTRAP_PASSWORD not set; skipping admin bootstrap."
fi

# ---- Start PHP-FPM in the background ----
# php-fpm writes its master PID to /usr/local/etc/php-fpm.pid (or
# similar). We capture it so the SIGTERM trap can stop it cleanly.
echo "[entrypoint] Starting PHP-FPM..."
php-fpm -D
PHP_FPM_PID="$(pgrep -f 'php-fpm: master' | head -1)"
echo "[entrypoint] PHP-FPM master PID: ${PHP_FPM_PID}"

# ---- Signal handling ----
# Render sends SIGTERM on shutdown. We forward it to both PHP-FPM
# and nginx, wait briefly, then exit. tini (PID 1) reaps children.
shutdown() {
    echo "[entrypoint] Received shutdown signal."
    if [[ -n "${PHP_FPM_PID:-}" ]] && kill -0 "$PHP_FPM_PID" 2>/dev/null; then
        kill -TERM "$PHP_FPM_PID" 2>/dev/null || true
    fi
    nginx -s stop 2>/dev/null || true
    sleep 1
    exit 0
}
trap shutdown TERM INT

# ---- Start nginx in the foreground ----
# This is the main process; when it exits, the script exits and
# tini reaps any remaining children.
echo "[entrypoint] Starting Nginx on port ${PORT}..."
exec nginx -g 'daemon off;'
