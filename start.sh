#!/usr/bin/env bash
# One-command new local installation; never reset or adopt existing data.
set -euo pipefail
umask 077
chrono_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
if [ -z "${CHRONO_DATA_DIR:-}" ] && [ -f "$chrono_root/.chrono-data-dir" ]; then
    IFS= read -r CHRONO_DATA_DIR < "$chrono_root/.chrono-data-dir"
fi
chrono_data="${CHRONO_DATA_DIR:-$HOME/.local/share/quodara-chrono}"
case "$chrono_data" in /*) ;; *) echo 'CHRONO_DATA_DIR must be absolute.' >&2; exit 1;; esac
case "$chrono_data/" in "$chrono_root/"*) echo 'Private data must be outside the checkout.' >&2; exit 1;; esac
mkdir -p -- "$chrono_data"
chrono_data="$(cd -- "$chrono_data" && pwd -P)"
chrono_env="$chrono_data/local.env"
chrono_image="${CHRONO_LARAVEL_IMAGE:-chrono-laravel:local}"
[ "$(id -u)" -ne 0 ] || { echo 'Run as your normal Docker-enabled user.' >&2; exit 1; }
[ "$(stat -c %a "$chrono_data")" = 700 ] || { echo 'Private data directory must have mode 700.' >&2; exit 1; }
if [ -e "$chrono_env" ] && [ ! -f "$chrono_data/installation-ready" ]; then
    echo 'An incomplete installation exists. Inspect it; nothing has been reset or retried.' >&2; exit 1
fi
docker build -t "$chrono_image" "$chrono_root"
if [ ! -e "$chrono_env" ]; then
    [ -z "$(find "$chrono_data" -mindepth 1 -maxdepth 1 -print -quit)" ] || { echo "Nonempty private directory without local.env: refusing adoption." >&2; exit 1; }
    mkdir "$chrono_data/.installing" || exit 1
    trap 'rmdir "$chrono_data/.installing" 2>/dev/null || true' EXIT
    chrono_host="${CHRONO_HOST:-}"
    if [ -z "$chrono_host" ]; then
        chrono_host="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") {print $(i+1);exit}}' || true)"
    fi
    chrono_host="${chrono_host:-localhost}"
    chrono_port="${CHRONO_HTTP_PORT:-8080}"
    [[ "$chrono_port" =~ ^[1-9][0-9]{0,4}$ ]] && [ "$chrono_port" -le 65535 ] || { echo 'HTTP port must be between 1 and 65535.' >&2; exit 1; }
    chrono_default_origin="http://$chrono_host"
    if [ "$chrono_port" != 80 ]; then chrono_default_origin="$chrono_default_origin:$chrono_port"; fi
    chrono_origin="${CHRONO_ORIGIN:-$chrono_default_origin}"
    printf '%s\n' "CHRONO_LARAVEL_UID=$(id -u)" "CHRONO_LARAVEL_GID=$(id -g)" \
      "CHRONO_LARAVEL_PRIVATE_PARENT=$chrono_data" "CHRONO_LARAVEL_IMAGE=$chrono_image" \
      "CHRONO_LARAVEL_INSTANCE=" "CHRONO_LARAVEL_ORIGIN=$chrono_origin" \
      "CHRONO_HTTP_PORT=$chrono_port" "CHRONO_BIND_ADDRESS=${CHRONO_BIND_ADDRESS:-0.0.0.0}" \
      "CHRONO_PROJECT_NAME=${CHRONO_PROJECT_NAME:-chrono}" > "$chrono_env"
    docker compose --env-file "$chrono_env" -f "$chrono_root/deploy/laravel/compose.yaml" run --rm operator chrono:prepare-fresh --private-parent=/var/lib/chrono-laravel --new-only --local-browser-first-admin > "$chrono_data/preparation-receipt.json"
    chrono_instance="$(sed -n 's#.*"directory": "/var/lib/chrono-laravel/\(chrono-fresh-[a-f0-9]*\)".*#\1#p' "$chrono_data/preparation-receipt.json")"
    [[ "$chrono_instance" =~ ^chrono-fresh-[a-f0-9]{24}$ ]] || { echo 'Preparation unconfirmed. Inspect the private receipt.' >&2; exit 1; }
    sed -i "s/^CHRONO_LARAVEL_INSTANCE=$/CHRONO_LARAVEL_INSTANCE=$chrono_instance/" "$chrono_env"
    docker compose --env-file "$chrono_env" -f "$chrono_root/deploy/laravel/compose.yaml" run --rm operator chrono:application-key --instance="/var/lib/chrono-laravel/$chrono_instance" --new-only --stopped
    docker compose --env-file "$chrono_env" -f "$chrono_root/deploy/laravel/compose.yaml" run --rm --entrypoint php -v "$chrono_root/deploy/laravel/prepare-terminal.php:/run/prepare-terminal.php:ro" operator /run/prepare-terminal.php "/var/lib/chrono-laravel/$chrono_instance"
    : > "$chrono_data/installation-ready"
fi
docker compose --env-file "$chrono_env" -f "$chrono_root/compose.yaml" up -d --wait --wait-timeout 90
printf '%s\n' "$chrono_data" > "$chrono_root/.chrono-data-dir"
sed -n 's/^CHRONO_LARAVEL_ORIGIN=/Open: /p' "$chrono_env"
