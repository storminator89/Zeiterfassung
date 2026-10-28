# Separate Laravel candidate image

Read the [installation/operator contract](../../docs/framework/installation-docker.md)
first. Nothing here has been activated or accepted as production-ready.

Build only, from the repository root:

```sh
docker build -f deploy/laravel/Dockerfile -t chrono-laravel:local .
```

The default image command lists Artisan commands and exits. `compose.yaml` has
only a one-off operator service; no listener. `compose.web.yaml` is an additional,
explicit TLS web profile, with no automatic provisioning or dependencies.
The root Dockerfile and native installation remain unchanged.

For a separately authorized **NEW-only** API candidate, see
[the private preparation and optional API overlay](../../docs/framework/fresh-api-key.md).
`compose.api.yaml` is never included by default and does not replace the Web/TLS
or operational acceptance gates. No keys or configuration are activated here.
