# FreshWeb must retain its selected boot invariants

Source anchor: Main `303329f7c03325b5e4f53d8791e368ca9e9a5369`, tree
`ad25a43e2b8a2b45ea87a539b29e66841e1de856` after PR279/280/281/282. This corrects an
existing profile-recheck gap; it adds no Runtime admission or new capability.

Initial FreshWeb selection already requires production, debug off, bcrypt, the
exact stock Web session guard and disabled terminal HTTP ingress. Its supplied
application key must support AES-256-CBC with no previous keys. These are trusted
operator prerequisites, not values the wrapper is authorized to repair.

Previously `assertCurrent()` rechecked captured instance metadata and effective
overrides but not those initial invariants. A later configuration change could
therefore pass the actual pre-cookie/session `RequireFreshWebProfile` middleware
even though the same settings would be refused at initial profile selection.
It also accepted a different valid key: checking initial key format alone did
not bind subsequent requests to the selected encryption identity.

## Fixed existing gate, no automatic configuration changes

`FreshWebConfiguration` now retains only a private SHA256 fingerprint of the
already-supplied decoded key bytes. It neither stores another raw key copy nor
constructs an Encrypter, generates/reads a key file, rotates a key or installs a
fallback. Before private metadata checks, `assertCurrent()` rechecks the original
production/debug/hasher/guard/terminal conditions, the existing cipher/previous-key
rules, and constant-time equality of that fingerprint. This also applies when
`configure()` calls `assertCurrent(..., false)` before overriding configuration;
that flag skips only effective override checks, not the boot invariants.

The exact same decoded key bytes supplied as raw text or valid base64 remain
the same identity. An unrelated display-only `app.name` change remains allowed.
Existing immutable overrides, Runtime/read/write flag checks, origin checks,
private identity and setup/closure policy remain unchanged. A mismatched setting
is refused, never overwritten or repaired. The existing middleware catches a
refusal as fixed no-store503 before its downstream cookie/session stack.

The entire Runtime implementation, original V5 pre-open hold, eligibility gate,
PR275 lifecycle binding, Setup, writer and authentication flows are unchanged.
The default/off profile still returns before reading a key or private metadata.
V5 does not gain a route, PDO, credential or recovery capability through this fix.

## Bounded credential-free evidence

The byte-exact published original class accepts all ten forbidden drift cases
through real supported local and existing-LDAP V4 profiles and their actual login
route middleware, with a counting-only downstream. The held V5 captured profile
likewise accepts all ten metadata changes; no V5 route is fabricated. The cases change environment,
debug, hash driver, default/Web guard, terminal ingress, a different valid or an
invalid key, cipher and previous keys. On the fixed source, the same cases refuse.
Restoring the selected values, changing only display metadata, and re-encoding
the same public example bytes continue to pass. Existing Runtime-flag refusal
remains a refusal.

Checks use actual standard-provider boot on new disposable credential-free
cohorts. The V5 boot still reaches exactly its original Runtime hold. Existing
public non-secret literal App-key format examples are supplied in memory only;
no password-hash/key/token generator, login, account provisioner, secret file or encryption
operation is called. Encrypter/session store/writer remain unresolved; a normally
constructed AuthManager is not an authenticated guard or login. The probes resolve
no additional services and preserve all DB/manifest bytes. Shared source bytes
remain unchanged; the edited PHP file has its own inode in the isolated checkout.

This is middleware/metadata component evidence, not real HTTP, cookie/session,
browser, deployment, concurrency, key-rotation, crash/restore or human recovery
acceptance. The baseline comparison explicitly loads the published original
profile class only; its content hash is recorded, and it has no location-relative
behavior. Other App sources and the original Runtime hold are unchanged in both
comparisons. No positive V5 Runtime/source admission, manual GitHub-CI run,
operating credential, host/security setting or remote action is involved.
