# Local Laravel authentication presentation

A narrow follow-up to protected first-admin setup. Laravel login, ordinary
registration and first-admin setup now render their existing card/form structure
without external executable code or remote presentation dependencies. Native
PHP pages, authenticated layout, controllers, validation, routes, CSRF/session,
setup-token/registration/role rules, dependencies and fixture admission do not
change. This is not activation or actual secret-entry/browser acceptance.

## Removed dependencies and local replacement

The three standalone auth views previously loaded the remote Tailwind runtime.
That JavaScript ran in the credential page's origin and could therefore inspect
sensitive form fields. A remote script's URL/version is not a secret-entry trust
boundary. It is removed, not delayed until after entry or hidden behind consent.

External stylesheets can also change the presentation of a security-sensitive
form and load additional resources; they are not described as harmless simply
because they are CSS. The three views consequently no longer request DaisyUI,
unused FontAwesome or Google Fonts either. Their finite card/input/button/alert
and utility styles are supplied by one fixed local Blade CSS partial. No remote
font, CSS import, URL, dynamic stylesheet generator, Node build, package install,
new asset-serving route or fetched/vendor dependency is introduced. The existing
local hummingbird image/favicon and fixed same-origin form actions stay exact.

Colors, card width/radius/shadow, logo dimensions, spacing, fields and button
identity are retained as local rules, not claimed pixel-identical. Poppins has no
tracked local font file; it is used only if already available locally, otherwise
the system sans-serif fallback applies. This can change typography/line wrapping.
Mobile rendering and a coarse visual comparison against the old public login
reference are still required. Merely compiling Blade/CSS does not establish them.

## Page-local policy, bounded claim

An early meta CSP on these three pages rejects scripts/objects/default remote
resources, permits inline local source CSS and same-origin images/forms, and
rejects a base-URL override. There are no scripts, inline event handlers, external
stylesheets or imports in these auth views/partial. This is a **meta** policy,
not an HTTP-header, frame-ancestors, TLS, proxy, browser-extension or whole-app
security claim. CSP behavior and actual network loads remain to be observed in a
supported browser before real secret entry. The remainder of the application and
native auth pages have not been converted to an offline asset set by this packet.

The source checks preserve entire form/field/CSRF/conditional markup by reversing
only the head/presentation changes. PHP/Blade/CSS syntax and a finite selector
inventory supplement the review; no runtime, server, credentials, account,
BrowserAuth action, screenshot capture or new CI is performed. Existing earlier
public-login imagery is reference evidence only, not a new visual pass.
