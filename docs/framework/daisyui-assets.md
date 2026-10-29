# Existing DaisyUI3.9.4 stylesheet: local immutable bytes

The ordinary Laravel layout now requests `assets/vendor/daisyui/3.9.4/full.css`
in the same position and under the same nonce/auth-style condition as the old
DaisyUI CDN link. Markup, classes, themes, CSS order, sensitive-page exclusion,
CSP, routes and other assets are unchanged. The browser checks the fixed SHA256
integrity attribute; there is no CDN fallback or runtime downloader.

## Provenance

The checked-in CSS is byte-for-byte the existing exact-version CDN response and
`package/dist/full.css` from the official `daisyui@3.9.4` npm distribution. The
archive matches its registry SHA512 integrity and SHA1, and is recorded with its
SHA256 in the adjacent `provenance.json`. The license matches the official Git
tag's blob. The stylesheet contains only embedded/fragment resource URLs and
no external `url()` or `@import` dependency.

- [Official repository/tag](https://github.com/saadeghi/daisyui/tree/ff71597b8b299b92fc3fdbfbc5ab06de66b67324)
- [Exact npm metadata](https://registry.npmjs.org/daisyui/3.9.4)
- [Previously used exact CSS URL](https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css)

No npm install, dependency update, package/lifecycle script, build tool, browser,
application instance, key, account, host/security setting or deployment was used.
The source package was read as a bounded archive; only three exact regular text
members were read, never extracted/executed as software.

## Boundaries

This closes one external-render dependency, not all of issue #67. Existing
FontAwesome/fonts, Source Sans, Flatpickr/SweetAlert/Shepherd styles and native/
extension CDN dependencies remain outside this slice. Existing local Chart.js
and FullCalendar packages are not rebuilt or replaced. A source change is not a
complete release image, Docker reproducibility, migration, restore or rollout
proof. Byte equality preserves the previous stylesheet; no new visual/browser
pass is claimed. The user performs browser acceptance.
