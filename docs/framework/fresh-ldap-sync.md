# Explicit FreshWeb LDAP Sync — source integration only

This slice connects the already reviewed Admin Sync mount to the explicit FreshWeb profile. It does not create accounts, keys, an operational profile or directory connections. Default remains off. This is not Native cutover, a running installation, or real LDAP acceptance.

## Exact source composition

Actual observed Main is `e8178933cb89a0a7e425ef37aec0bfe945fc4e3e` / tree `8effdf7e70fceca64599121ab305f4f98f941e6d`. The **local-only** prerequisite `538368f1cef4c667f922ec616b2b2a43289c7bbd` / tree `51b179c3c232ba88bdc98631035b5e2edd4a2dde` adds exact reviewed Login4 `a42c408e`, Form5 `f8c8536e`, Mount16 `dd2f89f6`; these are not silently called Main. Existing Setup/Auth, corrected policy, V4 admission and guarded Writer remain from actual Main. Routes/Provider contain an explicitly documented mechanical union of the exact Setup and Mount hunks; assembled independent review remains required.

The new delta is seven paths: `FreshWebLdapSyncProfile`, `LdapSyncFileSources`, `PinnedLdapSyncTransport`, existing `FreshWebConfiguration`, `FreshWebServiceProvider`, `chrono_ldap_sync.php`, and this document. No Auth/login/guard, Admin controller/UI/CSP/CSRF/throttle, Writer/schema/policy, fixtures, provisioning, API key or Native code changes. AppKey4, API, Denial/Latest, user mutations and held #197 changes are not bundled as dependencies or authorization.

## Explicit enablement and local break-glass

`chrono_ldap_sync.fresh` is trusted operator metadata, independent of `chrono_ldap` LOGIN metadata. It defaults to `['enabled' => false]`; disabled metadata is not used to discover files or targets. No environment secret, request field, settings-table row or inherited Native constant selects a Sync source.

Enabled metadata requires the existing writable V4 FreshWeb profile, setup opt-in **false**, and the monotone setup-closed marker at initial and every existing FreshWeb request admission. The original marker contract (any existence closes) is preserved. The marker is **not** proof that a local Admin exists: before an operator performs the cold post-setup switch, they must independently verify ordinary local Admin login and a recovery path. LDAP Sync is mounted by the existing registrar only from the captured profile; HTTP/registration capabilities alone do not grant intake permission.

No CA/key read, extension check or directory liveness probe occurs while loading configuration, admitting this metadata, constructing bindings, target-only Admin GET, or logging in locally. A missing/changed CA or root denies only an actual Sync operation after its fresh local Admin/persisted LDAP policy check. The unchanged local account provider still validates local hashes locally; directory failure does not become local-hash fallback for an LDAP account. Enabling Sync does not enable LDAP login, and disabling intake does not disable existing-account login. Bad **operator metadata** intentionally fails profile admission; local break-glass liveness independence is not a promise to ignore corrupt configuration.

## Metadata contract (no actual values are provisioned here)

The enabled `chrono_ldap_sync.fresh` array has exactly:

* `enabled: true`, `version: 1`;
* one canonical `uri` (`ldap://host:port` with verified StartTLS), nonempty unchanged `base_dn`, integer `max_entries` in 1..10000;
* `database_profile_sha256`, the versioned digest returned by the pure `LdapSyncFileSources::databaseProfileDigest(RuntimeAdmission)`;
* `ca` and `settings_root`, each an exact descriptor with `path`, `device`, `inode`, `owner`, `mode`, `parent_device`, `parent_inode`, `sha256`. Identity/mode fields are integers, SHA256 is lowercase hex. Inputs must be prepared through a separately authorized private operator process; this package does not inspect or produce real descriptors.

Both paths must be canonical absolute paths with no empty/dot/parent segments and must differ. The root basename is fixed **`encryption.key`**, never `jwt.key`, a Laravel Appkey, submitted bind password or default public constant. All file sources are outside the checkout and the actual application/public roots supplied by the FreshWeb Application (also for a non-default public path) and are later checked with `lstat`, canonical path, owner, exact mode, single-link regular-file identity and pinned device/inode; ancestors cannot be symlinks or untrusted writable directories. Root parent is additionally current-owner 0700 with pinned device/inode; root file is current-owner 0400/0600 and exactly 64 lowercase hex ASCII bytes. Native encryption uses `hex2bin` only inside the unchanged v2 AES-256-GCM cipher; JWT signing's ASCII-key contract is not reused.

CA file must be root/current-owner and exactly one of 0400/0444/0600/0644, nonempty and at most 1 MiB, with pinned identity, parent identity and SHA256. It is checked before official collection and again after all TLS/search/cleanup has completed, before the materialized import reaches encryption/Writer. The same immutable CA path is supplied to the unchanged official verified-StartTLS collector. A pin is operator admission metadata, not certificate-validation bypass or proof of reachability: certificate/name verification, bounded complete search, no referrals, cleanup and target pinning remain mandatory.

Reads use only `rb`, never create/open-for-write, chmod, repair, fallback, bootstrap, key rotation or secret caching. File path and opened-handle state are compared before/after bounded reads and hashes must match; fixed errors suppress paths, contents, fingerprints and previous diagnostics. Debug/JSON projections are reduced; object serialization is denied. Private descriptor hashes bind an explicit root/CA choice to one DB admission; **they do not independently prove that an operator selected the historical key matching existing ciphertext**. That provenance and consistent backup remain external responsibilities.

## Operation ordering and preservation

FreshWeb captures the exact normalized Sync metadata as a per-request configuration invariant. The new transport/cipher bindings are lazy and refuse to overwrite another admitted binding. GET calls only the pure immutable target getter, never files/cipher/collector. Existing orchestration reads the current local Admin/password version and persisted LDAP flag/revision before resolving sources. Collection then revalidates CA, performs the bounded official operation, and revalidates CA. The existing cipher reads the pinned existing root only when encrypting the submitted bind password. Writer rechecks current principal, explicit V4 profile, exact policy revision, enabled LDAP flag and all local-name collisions under its existing lock before any native import/settings write.

All-off Admin recovery, explicit policy CAS, CSRF, raw password bytes, persistent LDAP throttle, encrypted private sessions, local nonce/CSP credential document, blank bind fields, fixed no-store errors and bounded actor-bound result PRG remain unchanged from the reviewed prerequisites. No automatic retries, scheduler, automatic sync-on-login, local-to-LDAP conversion or first-visitor Admin path is added.

## Operational gates still open

* Fresh NEW preparation currently **does not create `encryption.key`**. This package deliberately cannot fill that gap by including `security.php`/`app_secret`: they create missing Native roots. A separately authorized private initial-root/provenance procedure is still required; until then Sync must remain off/unavailable. Never copy production keys into a synthetic test installation by implication.
* An authorized operator must separately admit exact metadata, CA provenance, matching existing root, private DB/profile identity and ordinary local Admin recovery; choose HTTPS/private runtime paths and request/worker/session-lock durations that cover the bounded directory operation. No claim of global deadline or lock-TTL/concurrency acceptance is made.
* Stop/drain before changing file identities, root, CA, admission or configuration. No long-lived-worker/config-cache support or live rotation is introduced. Recheck consistent DB/key backups and closures after restore; never reuse stale identity descriptors blindly.
* Actual LDAP extension/TLS/certificate/referral/size-limit/cleanup, HTTP/CSRF/auth/browser/mobile/visual and real account-effect acceptance remain separately authorized. No real file/secret/profile provisioning or such acceptance is performed here.
* Native V4/open_basedir and cutover holds, #29 LastLoginWins/auth-version gap, #30 central directory disable/session revocation gap, and held #197 remain unchanged. Constituent SOURCE CLEAR is not assembled runtime readiness or security certification.
