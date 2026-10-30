#!/usr/bin/env bash
set -euo pipefail
chrono_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
if [ -z "${CHRONO_DATA_DIR:-}" ] && [ -f "$chrono_root/.chrono-data-dir" ]; then
    IFS= read -r CHRONO_DATA_DIR < "$chrono_root/.chrono-data-dir"
fi
chrono_data="${CHRONO_DATA_DIR:-$HOME/.local/share/quodara-chrono}"
exec docker compose --env-file "$chrono_data/local.env" -f "$chrono_root/compose.yaml" stop
