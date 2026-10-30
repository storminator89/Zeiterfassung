# #42 — vacation requests in the standard Laravel application

## User workflow and deliberate rules

The existing attendance card keeps its vacation/illness selector and date inputs.
In the complete `vacation-requests-v7` profile, choosing **Urlaub** submits a
reasoned pending request, **without a time credit**. Its link opens request cards
inside the same Quodara layout, colors, typography and protected form stack.
There is no replacement UI or frontend framework. Own and review queues have
25-row pages and explicit state selection; decisions and reasons remain visible.

- Only the owner requests, withdraws a pending request, or requests cancellation.
- Only another currently authorized administrator, or the owner's current direct
  supervisor, approves/rejects vacation or its cancellation. An administrator
  cannot approve their own request. Role, team membership, active account,
  password fingerprint and account generation are checked freshly under the
  same write lock, including before returning an old command receipt.
- Approval requires a fresh read-only preview of the actual historical work
  model, stored holidays and current overlapping bookings. A changed plan hash
  or request revision refuses the decision; reloading is not implicit approval.
  Free/holiday days are skipped by the existing absence rules. A zero-credit
  request is shown but cannot receive a misleading zero-credit approval.
- Cancellation first becomes `cancel_pending`; original credits remain until a
  separate authorized decision. Approval deletes only the exact mapped IDs whose
  complete nine stored fields still equal their immutable originals. A changed,
  removed or ambiguously linked credit refuses the **whole** cancellation.
  Existing correction requests remain available; later legitimate corrections
  do not rewrite historical vacation decisions or silently become cancellations.
- **Krankheit remains its existing separate direct self-recording process.** It
  does not become a vacation request or acquire an invented approval policy.

An actor-free command carries an exact 32-lowercase-hex command ID. Its canonical
payload and immutable receipt provide idempotency before the revision check,
not before current authorization. A reused ID with different content refuses.
A successful historical replay does not assert that the request is still in that
old state: the page reloads current state. A query flag cannot attest a write:
HTML shows only neutral current-snapshot text, never a forged green confirmation
after a failed operation or a `saved=1` link. No automatic retry follows an unknown
commit/network result. Approval has one IMMEDIATE transaction for credits,
request revision, transition, exact mapping and receipt; cancellation likewise.
Competing decisions with the same revision cannot both commit.

Reasons are nonempty, trimmed, single-line UTF-8, at most 2000 **bytes**, without
control characters; do not enter sensitive medical/personal details. Date input
uses canonical real Berlin calendar dates, excludes year 0000 and permits at
most 366 inclusive days. These are bounded command rules, not a contract calendar.
There is no leave entitlement, remaining-leave/carry account, half-day rule,
retroactive employment assumption or external payroll policy in this feature.

## Web transport and existing API contract

This feature adds **no Bearer REST or MCP vacation endpoint**. Existing
`/api.php/login`, attendance/clock, own timeentries, correction-status and
work-model contracts remain intact. A pending vacation request is not a
`zeiterfassung` row and therefore never appears as booked credit in
`GET /api.php/timeentries` or its `entry_kind=absence` view. Approved credits
appear through that existing own read; an authorized completed cancellation
removes its exact original credit rows. `GET /api.php/corrections` remains booking
corrections, not the vacation queue. No extra approval authority or response
fields are inferred for an API/MCP wrapper.

The browser uses the ordinary protected Web/session/CSRF stack, not API tokens:

- In V7, the existing `POST save.php` vacation form sends `beschreibung=Urlaub`,
  `urlaubStart`, `urlaubEnde`, `vacation_reason`, `vacation_command_id` and normal
  CSRF. Successful JSON keeps `success`/`message` and adds the committed domain
  receipt: exact decimal-string `request_id`, integer `revision`, state/action,
  string credit/deletion ID lists. Its transport-only `next_command_id` advances
  the form only after confirmed success; unknown outcomes do not change the
  original command's replay identity. Illness retains the original form path.
- `GET vacation.php` only reads authorized own/review queues and an explicitly
  selected approval preview. `POST vacation.php` accepts the closed actor-free
  command for request, withdrawal, review or cancellation and returns an ordinary
  303 redirect after confirmed completion. Current state is reloaded; a query
  parameter cannot attest a write. Follow-up commands carry exact request ID,
  revision, reason and command ID; approval additionally confirms the displayed
  plan hash. Actor, role, account source/generation and audit identity are never
  accepted as input fields.

These HTML/form endpoints are not advertised as machine REST APIs. A future
MCP/API vacation extension must deliberately expose the same current authority,
team/owner, four-eyes, revision/plan and command-replay rules; it must not proxy
around CSRF, repurpose a correction endpoint or auto-approve on the user's behalf.
The explicit offline V5→V7 handoff changes DB-bound key admission metadata, not
JWT wire claims/signing-key bytes or ordinary token expiry/revocation rules.

## Domain persistence, not an audit-log queue

`vacation_requests`, append-only `vacation_transitions` and immutable
`vacation_credits` are the domain system of record. They have indexed owner/state
queries, unique actor/command and request/revision keys, linked state transitions
and explicit source snapshots. Ordinary `audit_log` remains the existing audit
trail, including original credit INSERT/DELETE operations; it is not repurposed
as a vacation queue. Existing bookings, account/password/generation semantics,
policies, login counters and old audit rows are not rewritten or backfilled.
Old vacation credits are not fabricated into newly approved requests.

V7 is an explicit independent profile with application versions
`[1,2,3,4,5,7]` and a five-row owned framework ledger/signature chain. It neither
adopts nor implements the separately held V6 work. `isAccountLifecycleV5()` stays
strictly V5. The new shared generation capability is pure validated manifest
selection, **not** evidence of a physical open; each actual Runtime open/writer,
policy read and snapshot retains its complete source/ledger/profile admission.

## New standard installation

The ordinary standard installation selection remains:

```sh
php artisan chrono:prepare-fresh --private-parent=/canonical/private/parent --new-only --local-browser-first-admin
```

It now prepares complete V7 and the same first-user policy. It still creates no
account, password, key or listener. The existing application/API/LDAP key tools,
first-admin/browser setup, encrypted non-expiring-session lock, shared login
budget, normal login, existing API, LDAP account authority, terminal operations
and database snapshot composition accept this **separately validated** profile.
An explicit `--vacation-workflow-v7` without the browser option prepares the same
schema for the existing deliberate CLI first-admin procedure.

The unflagged historical V4 file producer and explicit
`--dormant-account-lifecycle-v5` producer remain available with their original
contracts. They do not gain first-user takeover of existing data. This feature
does not activate API/LDAP/terminal, create real credentials or start a service.
See [the standard installation](laravel-default-installation.md).

## Existing standard V5: explicit same-cohort upgrade

This is **not NEW-only**. Existing canonical **closed** V5 standard installations
have a fixed offline additive handoff. Finish any still-open first-user setup
first; never reopen setup, regenerate an App-key, reset a password or reinstall
as a migration shortcut. Existing V5 can continue using its original functions
until the deliberate upgrade, and is never silently stopped by publication.

1. Retain the matching source and a complete private deployment/cohort backup.
   Stop and drain **all** Web/API/LDAP/terminal/CLI consumers and exclude restarts.
   `--offline` is the operator's assertion, not discovery or proof of shutdown.
   Use the same supported single-host, non-root owner/UID and original cohort
   path/mount; do not move/copy/adopt a cohort or repair permissions.
2. Prepare a distinct private, single-link, byte-identical **current cold V5 DB
   backup**, outside webroots. SQLite must use its admitted rollback-journal
   configuration with no WAL/SHM/journal sidecars. An old/stale backup refuses.
3. With the ordinary operator command profile (Runtime/Web disabled), run:

   ```sh
   php artisan chrono:vacation-workflow-v7 --instance=/canonical/private/parent/chrono-fresh-EXACT_SUFFIX --backup=/canonical/private/backup/current-v5.sqlite --offline
   ```

   The command takes the existing nonblocking `setup-operator.lock`, verifies the
   complete V5 schema/ledger/cohort, then writes the private pending fence before
   schema mutation. Additive DDL, marker 7 and ledger row 5 commit in one IMMEDIATE
   transaction, with all existing source rows, policy, counters and sequences
   verified unchanged. The DB inode/path, passwords, generations, original keys,
   App-key and session/cache directories are preserved.
4. Only recognized nonsecret API-key metadata/claim and LDAP-settings-root
   metadata are rebound from their exact old value to the exact new DB profile.
   **No key bytes are read, generated, overwritten or rotated by the upgrader.**
   Fixed metadata is handed off using private exclusive staging files; the new
   runtime manifest is last. A complete private receipt precedes fence removal.
   Runtime, FreshWeb, API-management and Web entrypoint refuse a pending fence.
5. Keep consumers stopped until their existing trusted configuration has reloaded
   the new manifest and any configured channel admission metadata. Existing
   cookies/JWTs retain the same account/key/origin authority (subject to ordinary
   expiry/revocation); a cached old manifest/profile correctly refuses. The
   command does not rewrite an operator EnvironmentFile, enable a channel,
   restart workers or perform deployment.

For separately configured external API signing-key metadata, terminal roots or Fresh LDAP sync, provide their
**current nonsecret metadata**, not key bytes/passwords, in a canonical private
JSON input to `--channel-metadata=/canonical/private/channels.json`. Its exact
shape is `{ "api": null-or-existing-api-key-metadata, "terminal":
null-or-existing-device/card-object, "ldap_sync":
null-or-existing-enabled-sync-profile }`, with keys in that order and the same
pretty JSON/newline format as the existing private manifests. The existing
validators must admit each input against the old DB; all root paths, identities,
fingerprints, existing 0400/0600 key modes, URI/CA and non-profile fields are retained. The private output
`vacation-v7-channel-admissions.json` contains their exact rebound objects for
the **existing** trusted configuration transport. Omitting them does not turn
those integrations off or fabricate replacement metadata: stale external
configuration remains stale and will refuse until explicitly rebound/reloaded.
The command never follows an input path to generate/read a root or contact LDAP.

A failure can occur after confirmed schema commit but before metadata completion.
Keep everything stopped, retain the DB, original backup and private record, and
inspect the outcome. Only an explicit `--resume` accepts the exact recorded old
or complete new schema, unchanged original backup/source evidence, and exact
known old/new metadata. Unexpected or partial staging bytes are **not repaired,
removed or overwritten**. There is no blind retry, downgrade, automatic restore,
key reset, arbitrary file adoption or claim of cross-file/power-loss atomicity.
A completed repeat only verifies the still-stopped immediate post-upgrade state;
it is not a restore, maintenance mode or later operational reconciliation tool.

This preserves existing features across the standard V5→V7 upgrade, rather than
creating a parallel web-only application. Original Native/V2 installations and
their direct absence behavior remain unchanged. Their supported Laravel cutover
is still the separate #74/#82 work; this feature does not certify that cutover,
apply to an unknown real installation, or close deployment/restore acceptance.

## Actual coarse evidence and limits

The source freeze, changed-path hashes, source tree, final reanchor, independent
reviews and natural CI run IDs are recorded in the accompanying PR/evidence.
Local runtime used PHP **8.5.12**, Laravel **13.38.0**, SQLite **3.46.1**; all
accounts, records and keys belonged only to newly allocated disposable fixtures.
No real credentials, personal operational data, network service or host changes
were used. README media and existing third-party/public UI assets are retained;
only the two existing attendance-script copies gain a narrow confirmed-command
ID advance, with its exact FreshWeb integrity pin updated, not weakened.

- Canonical Memory schema/reference journey: old profiles 2/3/4/5 retain exact
  admission object/JSON/serialization/ledger behavior; immutable domain history,
  retained correction/tombstone relationships and unchanged old schema validated.
- Direct admitted V5/V7 file journey: policy, API operating-policy reads, login
  budget, exact clock replay, current generation revocation, authorized native
  snapshot export/discard, and pending-upgrade refusal.
- Actual same-cohort V5→V7 handoff, including deliberate interruption after DB
  commit, explicit known forward resume, unchanged existing key bytes/inodes,
  unchanged accounts/source and rejection of the old manifest. Actual Artisan
  parsing also preserves separately configured external API/terminal/LDAP source
  metadata and 0400 root modes; the existing API/terminal file readers accept the
  rebound original sources. No LDAP/network/device operation is claimed. NEW7
  works with the same existing key and first-admin tools.
- **51 real Kernel requests**, a new ordinary Application each request: existing
  first-admin/admin-team/model setup, actual offline Artisan upgrade, original
  encrypted worker cookie and pre-upgrade JWT survive metadata reload, original
  absence form creates Pending without credit, direct supervisor preview and
  exact replay approve one 300-minute credit, owner cancellation plus separate
  review removes that original only, rejection/withdrawal, self/outside-team
  refusals, direct illness, existing JWT clock start/end and fresh protected
  views; integrity and declared relationships remain valid. A forged `saved=1`
  GET and failed POST show no green write confirmation; neutral current state is
  displayed after a successful read. The final fixture explicitly sends German
  Accept-Language rather than assuming Symfony's English default is German.
- **Two separate PHP processes** released at one barrier with different approval
  IDs but the same request revision: exactly one confirmed approval, one 409,
  one credit and one approval transition; integrity remains valid.
- Existing **21-request** standard installation rehearsal now reports actual V7:
  genuine first-user/CSRF/encrypted cookies, clock/pause/replay/logout/new-boot
  login/history/export and altered-asset refusal, with disposable cleanup.
- Domain Memory journey additionally covers changed payload, changed current
  account/team before replay, Berlin DST and holiday planning, stale plan,
  cancellation rejection, real correction changing a credit blocks cancellation,
  late mapping failure rolls the whole operation back, and cancellation with an
  existing pending correction retained for clarification.

These are coarse functional checks, not a new repository test suite or manual
CI dispatch. They do **not** certify a browser's rendering/navigation, actual
Docker/TLS/mount/worker shutdown, live LDAP bind/sync, external terminal device,
large-history performance, power-loss recovery, legal leave policy, arbitrary
restore or real deployment. Full reference/relationship checks currently run
multiple times on the V7 path; no large-dataset latency claim is made. Those
operating boundaries and #74/#82 remain honest open work, not implied passes.
