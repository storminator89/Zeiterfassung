# Laravel installation and operator tools

The repository-root `Dockerfile` and `compose.yaml` are the **Laravel Web default**.
Use the [NEW local vacation-workflow V7 standard installation](../../docs/framework/laravel-default-installation.md):
explicit private preparation/App-Key/TLS, then root `docker compose ... up -d`.
The application user registers first Admin in the browser without a token.
No real installation, credentials or listener are created by these source files.

The files in this directory retain the detailed explicit operator/alternative
composition. Read the [full contract](../../docs/framework/installation-docker.md).
Their Dockerfile still lists Artisan commands and exits by default:

```sh
docker build -f deploy/laravel/Dockerfile -t chrono-laravel:local .
```

Its operator service has no listener/network/dependency startup;
`compose.web.yaml` remains an explicitly selected TLS profile, not an automatic
provisioner. Both images share the pinned runtime/locked dependency/build source;
root default execution differs deliberately. Always build the intended image/tag
before use. The native compatibility image is now `deploy/native/Dockerfile`.

For separately authorized NEW-only API keys/overlay, see
[the API preparation contract](../../docs/framework/fresh-api-key.md).
API/LDAP/terminal remain off during browser first registration. A source build
or running container is not browser/TLS/production acceptance.
