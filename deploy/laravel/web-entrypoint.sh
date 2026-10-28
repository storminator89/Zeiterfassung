#!/bin/sh
# Startup checks only. Never provision, migrate, reset, repair, source/eval a
# config file, print secrets, start a background task, or fall back to native PHP.
set -eu
umask 077
fail() { printf '%s\n' 'Laravel web startup refused; inspect the private operator configuration.' >&2; exit 1; }
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
    false) [ -e "$instance/setup-closed" ] || [ -L "$instance/setup-closed" ] || fail ;;
    *) fail ;;
esac
[ -r /run/chrono-tls/certificate.pem ] && [ -r /run/chrono-tls/private-key.pem ] || fail
# A syntax check may read the explicitly mounted TLS credentials but never
# returns their bytes. Failure has no automatic repair or alternate HTTP mode.
apache2ctl -t >/dev/null 2>&1 || fail
exec apache2-foreground
