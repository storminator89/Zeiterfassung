# Existing Font Awesome Free 6.4.2: local stylesheet and fonts

The ordinary Laravel layout now requests
`assets/vendor/fontawesome/6.4.2/css/all.min.css` in the same position and under
the same nonce/auth-style condition as the old exact-version CDN link. The raw
stylesheet, classes, icon mappings and all eight relative WOFF2/TTF font resources
are unchanged. Sensitive-page exclusion, CSP, other assets and CSS order are
unchanged. The stylesheet has a fixed SHA256 integrity attribute. There is no
CDN fallback, runtime downloader or font installation into the operating system.

## Exact distribution provenance

The authoritative bytes are the official **Free** npm package
`@fortawesome/fontawesome-free@6.4.2`. Its archive was checked against the exact
registry SHA512 integrity and SHA1, and is recorded by SHA256 in the adjacent
`provenance.json`. The CSS and every referenced font also match the previously
used exact-version cdnjs resources byte-for-byte. All relative CSS URLs resolve
inside the checked-in `webfonts` directory; no imports or external resources
remain in this stylesheet. This does not remove other stylesheets' dependencies.

The CSS and full license notice additionally match the public 6.4.2 Git tag.
**The eight published fonts do not match that tag's font blobs.** They are the
verified npm/CDN distribution bytes, not substituted tag builds. The package's
reported `gitHead` source could not be resolved publicly; neither a reproducible
build from source nor the historical CDN release pipeline is claimed. The
manifest pins the current cdnjs npm-autoupdate configuration to an exact commit
and blob as supporting distribution metadata, not as historical build proof.

- [Official package setup](https://docs.fontawesome.com/web/setup/packages)
- [Exact registry metadata](https://registry.npmjs.org/@fortawesome/fontawesome-free/6.4.2)
- [Public tag source](https://github.com/FortAwesome/Font-Awesome/tree/f0c25837a3fe0e03783b939559e088abcbfb3c4b)
- [Pinned cdnjs configuration](https://github.com/cdnjs/packages/blob/334566b7d74f780cbeac7c06219ab29b2bb77e53/packages/f/font-awesome.json)

`LICENSE.txt` is preserved in full: fonts use SIL OFL 1.1, icons CC BY 4.0 and code
MIT. This is **not** a blanket MIT license and includes no Pro package or assets.
Original stylesheet attribution is retained.

## Check and scope boundary

Checks cover registry/archive/file hashes, exact existing-CDN equality, all
relative font resources, the complete inverse layout diff and Blade compilation/
PHP syntax. No npm/package script, source build, font registration, app instance,
account, key, server, host/security change or deployment was used. Font bytes
were stored and compared, not executed by a browser or font engine. The user
performs browser acceptance; no new visual/browser pass is claimed.

This is one bounded part of issue #67, not its completion or a reproducible
Docker/release/restore claim. Other external CSS, dynamic/floating dependencies,
native/extension assets and complete release verification remain separate.
Existing local Chart.js and FullCalendar are not replaced or rebuilt.
