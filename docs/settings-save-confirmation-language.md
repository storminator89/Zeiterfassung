# Settings save confirmation follows the selected language

Small usability correction for #62, prepared against actual Main
`bb980b1f2af4d34aab8fa5d40f34c70103f18f90`, tree
`9104f0851fc3e6b1838e170bf890317bbfaa3c8a`. The existing preference form already
supports DE/EN/Traditional Chinese, but its success modal title/body/Close stayed
German after an English or Chinese selection. The same modal now uses two new
copy labels and the existing translated `BUTTON_CLOSE`, in both Native and Laravel.
German output remains byte-identical.

Only visible text changes. Native assigns the localized ordinary preference-save
message at its existing point; existing import-specific success text still
replaces it verbatim and is escaped at the unchanged output boundary. Import
consumption, error precedence, selected-language precedence and success-modal
eligibility remain unchanged. The shared close function, onclick attribute,
classes/styles/order and every save/password/carry-in/role/source/CSRF/SQL/transaction
branch remain unchanged. In particular, this does not integrate held PR340's
Native carry-in validation or change its historical behavior. No held/denied
packet, writer, reader, route, schema, dependency, runtime/host/security or deployment
change; no new submission or automatic retry.

## Coarse evidence and limits

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 and unchanged preexisting Composer
lock: one original-schema anonymous Memory component journey runs the actual
Settings controller for normal preference saves/GETs, DTOs and compiled actual
Settings content with an inert layout and RequestGuard identity double. DE/EN/ZH,
light/dark/system and user/Admin yield 18 confirmed ordinary preference screens
and 36 unsaved/error screens. All other complete rendered bytes, modal visibility,
existing session preference outcomes, source/schema/audit/change counters,
query_only, native globals and repeated renders are preserved.

The actual Native locale/classification, ordinary nonadmin preference branch,
import and modal fragments are rendered separately: six cases per language cover
ordinary save, query-language priority, unsaved GET, import, import overriding a
save with existing error, and password-form classification without executing the
password handler. Six language files emit zero bytes and their new copy matches.
Only the three intended visible labels differ; import text and other state/markup
are exact. Necessary seven PHP source and compiled actual Blade lints pass.
The unchanged close script passes JavaScript syntax and repeated-close VM/fake-DOM
checks under Node 24.22.1. No new repository test suite, fixture server or manual
CI dispatch; natural exact-head/Main CI followed separately.

No real credentials/accounts/keys/data/file DB, password or carry-in command,
Native bootstrap, full App/login/auth/CSRF/HTTP/browser/layout/theme persistence,
production/concurrency or deployment acceptance. The Native fragments do not
prove the whole native page. #62 remains partial/open; broader onboarding,
account/recovery/session policy and browser acceptance are not claimed.

Frozen evidence: `/tmp/chrono-settings-save-copy-mainbb980b1f-support/` contains
coarse.json, native-de/en/zh.json, close-coarse.json, proof.json, candidate.patch,
complete-trees.bundle and independent-review.json. Merge/CI evidence is separate.
