#!/bin/sh
# Startup and explicitly requested stopped cache-lock cleanup only.
# Never provision/migrate/reset accounts, data, keys or setup; never repair
# admission/config files, source/eval config, print secrets, start background
# tasks or fall back to native PHP.
set -eu
umask 077
fail() { printf '%s\n' 'Laravel web startup refused; inspect the private operator configuration.' >&2; exit 1; }
mode=web
case "$#" in
    0) ;;
    2) [ "$1" = --clear-stopped-cache-locks ] && [ "$2" = --stopped ] || fail; mode=clear_stopped_cache_locks ;;
    *) fail ;;
esac
# --stopped is an operator assertion, not discovery or proof of stopped workers.
[ "$(id -u)" -ne 0 ] || fail
[ "${APP_ENV:-}" = production ] && [ "${APP_DEBUG:-}" = false ] || fail
[ "${FRAMEWORK_FRESH_WEB_ENABLED:-}" = true ] || fail
[ "${FRAMEWORK_CHRONO_READ_ENABLED:-}" = true ] && [ "${FRAMEWORK_CHRONO_WRITE_ENABLED:-}" = true ] || fail
[ -n "${FRAMEWORK_APP_KEY:-}" ] && [ -z "${FRAMEWORK_PREVIOUS_KEYS:-}" ] || fail
case "${APP_URL:-}" in https://*) ;; *) fail ;; esac
instance=${FRAMEWORK_FRESH_INSTANCE_DIRECTORY:-}
case "$instance" in /var/lib/chrono-laravel/chrono-fresh-*) ;; *) fail ;; esac
# Full canonical path/UID/mode/file-identity/digest/schema and origin admission
# remain authoritative in the existing FreshWeb/Runtime guards, not this shell.
[ -d "$instance" ] && [ ! -L "$instance" ] && [ -f "$instance/runtime-admission.json" ] || fail
[ ! -e /opt/chrono/framework/bootstrap/cache/config.php ] || fail
for cache in /opt/chrono/framework/bootstrap/cache/routes*.php; do [ ! -e "$cache" ] || fail; done
case "${FRAMEWORK_FRESH_SETUP_ENABLED:-}" in
    true) [ ! -e "$instance/setup-closed" ] && [ ! -L "$instance/setup-closed" ] && [ -f "$instance/setup-profile.json" ] || fail ;;
    false)
        if [ -e "$instance/setup-closed" ] || [ -L "$instance/setup-closed" ]; then
            : # Original closed setup mode.
        else
            # Explicit NEW local-install profile only. Laravel still validates
            # its private identity/binding, local HTTPS origin and zero-user writer.
            [ "${FRAMEWORK_ADMITTED_LEGACY_API_ENABLED:-false}" = false ] || fail
            [ -f "$instance/local-first-user-setup.json" ] && [ ! -L "$instance/local-first-user-setup.json" ] || fail
            [ ! -e "$instance/setup-profile.json" ] && [ ! -L "$instance/setup-profile.json" ] || fail
            [ ! -e "$instance/operator-setup-token.txt" ] && [ ! -L "$instance/operator-setup-token.txt" ] || fail
        fi ;;
    *) fail ;;
esac
[ -r /run/chrono-tls/certificate.pem ] && [ -r /run/chrono-tls/private-key.pem ] || fail
# A syntax check may read the explicitly mounted TLS credentials but never
# returns their bytes. Failure has no automatic repair or alternate HTTP mode.
apache2ctl -t >/dev/null 2>&1 || fail
if [ "$mode" = clear_stopped_cache_locks ]; then
    # Original application boot must still admit this exact private Fresh store.
    # Clear ALL its cache locks only after external shutdown/restart exclusion.
    # Never clear sessions/cache/data/keys, reset a token or start an HTTP server.
    exec php artisan cache:clear file --locks
fi
exec apache2-foreground
