# Existing application architecture

> Historical pre-migration audit. The project now runs on CodeIgniter 4.7.4. See [current migration details](CODEIGNITER_MIGRATION.md) for the implemented structure and superseding findings.

Audit date: 2026-09-17. Scope: the application in `/opt/lampp/htdocs/tools`; read-only inspection and baseline checks, followed only by these audit documents. `AGENTS.md` and `docs/PRODUCT_SPEC.md` were initially empty; both were reread completely after the user saved them. The saved requirements govern this report and the companion [implementation plan](SAAS_IMPLEMENTATION_PLAN.md).

Branding update: the application now uses Mber Digital branding and the supplied logo; obsolete brand assets have been removed. The technical findings below describe the original audit unless explicitly updated.

## 1. Framework and version

There is **no application framework**: this is procedural PHP serving HTML with inline JavaScript and CSS. No Laravel, Symfony, React, Vue, root dependency manifest, ORM, or build pipeline is present. Local CLI PHP is **8.2.4**; this does not establish the production web-server PHP version. The QR library requires PHP **8.2+** and mbstring; PNG generation also uses GD.

| Dependency | Observed version and use |
| --- | --- |
| `chillerlan/php-qrcode` | 6.0.0, installed and locked in `qrcode/`; server QR generation |
| `chillerlan/php-settings-container` | 3.2.1, transitive PHP dependency |
| jQuery | 3.7.1, remote script in `qrcode/index.php:518` |
| Bootstrap Icons | 1.9.1 locked; SVG files used as QR logos |
| canvg | 4.0.1 locked; no active page import found |
| canvas2svg | Vendored header says 1.0.19; used for QR SVG download |
| FileSaver.js | Vendored, no active page import found; no version established |

Local PHP extensions checked: GD, mbstring, fileinfo, EXIF, PDO MySQL, PDO SQLite and session are loaded; Imagick is absent. Installed extensions do not imply the application uses a database or sessions.

## 2. Application architecture

Independent directory-based tools share branding rather than an application service layer:

```text
index.php                         Internal tools dashboard
assets/css/style.css              Dashboard styles
assets/img/printrivo_light_logo.png / printrivo_dark_logo.png     Mber Digital branding
qrcode/index.php                   PHP QR generation + HTML/CSS/jQuery/Canvas
qrcode/vendor/                    QR Composer dependencies
qrcode/node_modules/              QR npm dependencies; icons served directly
qrcode/js/                        Vendored browser libraries
dtf/index.php                     Redirect; unreachable legacy calculator below exit
dtf/builder.php                   Active gang sheet UI + rate-override POST handler
dtf/upload.php                    Metadata inspection JSON endpoint
dtf/inc/image_info.php            Image/DPI parsing and cleanup helpers
dtf/assets/js/upload-progress.js  Shared upload/progress and local PNG parsing
dtf/customers.json                Legacy customer contact records
dtf/tests/builder.test.cjs         Existing Node VM regression test
cleanimg/index.php                Browser-based image processing UI
```

No shared bootstrap, controllers, templates, repositories, service container, queues, scheduler configuration, persistent projects, or central error handler was found. Tool state mostly lives in browser memory. Source line references describe this snapshot.

## 3. Current routing structure

Routes are file paths, with directory index resolution dependent on the server. No `.htaccess`, route registry, virtual-host configuration, or front controller is supplied. Paths below are relative to the application mount point, not proof of deployed server behavior.

| Path | Behavior |
| --- | --- |
| `/`, `/index.php` | Internal dashboard linking to all three directories |
| `/qrcode/`, `/qrcode/index.php` | QR form, generation and result page |
| `/dtf/`, `/dtf/index.php` | PHP `Location: builder.php` then immediate `exit` (normally 302) |
| `/dtf/builder.php` | Active builder; POST also handles PIN/rate JSON |
| `/dtf/upload.php` | Image metadata endpoint |
| `/cleanimg/`, `/cleanimg/index.php` | Image cleaner page |
| `/dtf/customers.json` | Static legacy customer data; no application protection |
| `/qrcode/custom_logos/*` | Runtime uploads served as static files if server permits |
| `/qrcode/qr_code.svg` | Existing static artifact; active generator does not write it |
| `/assets/*`, tool assets and QR icons | Static resources |

`/tools/qr`, `/tools/gang-sheet`, `/tools/image-studio`, public marketing/auth routes and account routes do not exist yet. The subsequent Mber Digital rebrand replaced the legacy absolute home links in QR and the unreachable DTF calculator with application-relative suite links. Relative fetch, script and logo URLs need explicit handling when introducing friendly URLs.

## 4. Database configuration and schema/models

No database connection configuration, SQL schema, migration system, model classes, PDO/mysqli calls or database files were found in application code. Do not infer that an external database is configured simply because this workspace is under XAMPP.

`dtf/customers.json` is an array of two records with `company`, `contact`, `phone`, `email`, `address`, `notes`. Values are intentionally omitted here. Only the unreachable calculator fetches this file (`dtf/index.php:1563`). The active builder accepts customer text in browser state. These contacts are not login accounts or Stripe customers and must not be automatically imported as users.

Browser records include DTF artwork assets and placements (dimensions, DPI, quantity, coordinates, rotation, Blob URLs), cleaner items (source/current canvases, warnings, output Blobs), and transient QR form/results. None is persisted as application models.

## 5. Authentication

No registration, login, logout, password hashing, reset, verification, user lookup, roles, or authenticated access guard exists. `dtf/builder.php:2` checks a hardcoded four-digit PIN for rate override, using `hash_equals`; this is not account authentication. Its value is deliberately not reproduced here. There is no attempt throttling or persistent admin identity.

## 6. Sessions

No `session_start`, `$_SESSION`, application cookies, session backend, cookie policy or session expiration logic was found. Browser arrays and object URLs are page-local state; no localStorage, sessionStorage or IndexedDB persistence was found in first-party tool code. Reloading loses unsaved work. PHP's session extension is installed locally but unused.

## 7. Frontend/CSS

Custom dark styles, Arial/system fonts, grid/flex layouts and media queries. Dashboard CSS is in `assets/css/style.css`; each tool embeds its own substantial styles and scripts. No Bootstrap CSS framework is loaded: Bootstrap-style class names in the QR page have local styling, and Bootstrap Icons is an icon collection. DTF and cleaner use vanilla JavaScript, Canvas, File/Blob APIs and object URLs. QR uses jQuery and canvas2svg. No bundler or SPA router exists.

## 8. Printrivo QR Generator

`qrcode/index.php:1` loads its local Composer autoloader. POST fields include `content`, `logo_select`, `custom_logo_checkbox` and multipart `custom_logo`. Chillerlan renders a base64 PNG with automatic QR version 11–40, high error correction and scale 20. HTML carries that result into JavaScript.

The browser draws a 400px preview and overlays a selected SVG icon or custom logo. PNG export uses a 1500 × 1500 canvas (5 inches × 300 target DPI); the size calculation alone does not establish embedded PNG DPI metadata. SVG export draws the generated raster into canvas2svg, so it should not be described as a native vector QR encoding.

Custom logos are moved to `qrcode/custom_logos/` under randomized names. That directory is created at runtime and cleaned on QR requests with a one-hour TTL. Built-in icons are enumerated from `qrcode/node_modules/bootstrap-icons/icons/` and selected against that allowlist. Downloads are generated in the browser, not through a download API.

## 9. Printrivo Gang Sheet Builder

`dtf/builder.php` is the active implementation; the large calculator below `exit` in `dtf/index.php` does not run. Preserve that distinction during migration.

The builder uses a 16-inch sheet, DOM artwork positioning, drag/resize/rotate actions, automatic arrangement and length fitting, browser-side pricing with a $15 minimum and length-dependent rates, quote/invoice printing, and a 300 DPI PNG Canvas export with a pHYs chunk. These print-job prices are unrelated to SaaS subscription pricing.

`dtf/assets/js/upload-progress.js` reads PNG pHYs metadata locally; other/fallback image formats are posted to `upload.php`. Artwork pixels/previews remain in browser Blob URLs. The PHP endpoint returns metadata rather than a stored image URL. The builder also contains edge-color background removal and side-margin trimming (`prepareAsset`, `removeAssetBackground`, `trimAssetMargins`). The rate override POST returns a validated positive number; the browser holds the override. No quote, invoice, payment or project is saved server-side.

## 10. Image cleanup/background removal/upscale

`cleanimg/index.php` has only a strict-types PHP preamble; all image operations run in its inline JavaScript. This is the existing tool to brand as **Printrivo Image Studio**.

Files are decoded locally, DPI is parsed from PNG/JPEG/EXIF data, backgrounds are sampled from corners and removed with an edge-connected flood fill and feathering, transparent bounds are cropped, and output becomes transparent PNG with 300 DPI metadata. Upscaling uses high-quality Canvas interpolation, capped at 4× based on source DPI when enabled. Unknown DPI does not trigger scaling. This is not an AI segmentation or super-resolution service. Batch downloads are individual browser downloads. No processing API, external AI provider or remote upload is called by this page.

The DTF builder has a separate background-removal implementation; protecting the cleaner alone does not cover that functionality.

## 11. Existing tool endpoints

| Method and path | Input / output | Access today |
| --- | --- | --- |
| GET `/qrcode/index.php` | Form HTML; also creates/cleans logo directory | No application check |
| POST `/qrcode/index.php` | Multipart form → HTML containing QR result/logo path | No auth or CSRF |
| GET `/dtf/builder.php` | Builder HTML | No application check |
| POST `/dtf/builder.php` | JSON `{pin, rate}` → `{rate}`; 403 bad PIN, 422 invalid rate | Static PIN only |
| POST `/dtf/upload.php` | Multipart `image` → `{ok:true,image:{name,mime,widthPx,heightPx,dpi,dpiSource,dpiMissing,printWidth,printHeight,squareInches,warnings}}` | No auth or CSRF |
| Other methods `/dtf/upload.php` | 405 JSON error; cleanup runs before method check | No application check |
| GET `/dtf/customers.json` | Legacy contact data array | Static file |
| GET `/cleanimg/index.php` | Cleaner HTML; no backend processing endpoint | No application check |

DTF errors return `{ok:false,error,limits}` (400/413/415 or 500 as applicable). Upload progress uses XMLHttpRequest with a 600-second timeout. The cleaner exports locally, and QR downloads locally. The only observed external runtime script request is QR's jQuery CDN; no existing billing, authentication or AI API integration was found.

## 12. Upload/storage system

QR: persistent temporary public-directory uploads, extension allowlist only, randomized names, no ownership record, no explicit application byte/pixel limit. Cleanup is request-driven and scans all matching files irrespective of user. Idle sites retain files beyond the nominal TTL.

DTF: multipart files go to PHP-managed temporary storage, MIME-checked with fileinfo against JPEG/PNG/WebP/GIF, then inspected with `getimagesize`. Application byte cap is 500 MiB, subject to lower PHP/web-server limits. `image_info.php` optionally instantiates Imagick, otherwise uses EXIF/PNG parsers with 300 DPI fallback. The current upload handler does **not** move files into `dtf/uploads`; its 600-second cleanup helper addresses that legacy directory if it exists. PHP cleans its request upload temporary files normally.

Cleaner: no server storage; all files, canvases and outputs are in browser memory. No S3/object storage, ownership metadata, signed downloads or background cleanup scheduler is configured. Runtime upload directories were not present in the inspected snapshot.

## 13. Environment/configuration

No `.env`, `.env.example`, environment loader, `getenv`, `$_ENV`, application secret or named application environment-variable contract was found. Do not dump the host environment to establish this fact.

Existing inputs are PHP/server configuration: HTTPS/HTTP_HOST/REQUEST_URI for dashboard URLs; REQUEST_METHOD for handlers; CONTENT_LENGTH for upload diagnostics; `upload_max_filesize`, `post_max_size`, `memory_limit`, `serialize_precision`, and display-error settings. Prices, PIN, temporary TTLs, branding and legacy domain links are embedded in source. Hosting/vhost/proxy/TLS/session defaults and deployment secrets remain unverified.

## 14. Authentication/billing dependencies

None is installed as an application dependency. There is no Stripe SDK, mailer, auth package, OAuth client or JWT library. The QR package metadata mentions `chillerlan/php-authenticator` under its own development requirements/suggestions; it is **not** an installed application authentication implementation. Existing PHP capabilities can support password hashing, secure randomness and sessions; new database, mail, billing and migration infrastructure is needed.

## 15–18. Recommended SaaS seam, protection, billing and migrations

Add a shared root-level PHP bootstrap/security layer and an explicit route registry for new SaaS pages; preserve the tool implementations behind small adapters. Every existing executable entry point must call the same authorization policy before output, generation, cleanup or uploads. A front controller alone is insufficient if old physical files remain directly accessible. See the plan for friendly URL asset handling and endpoint compatibility.

Require authenticated, enabled users and an eligible locally synchronized subscription. Cover all tool pages, the builder POST and metadata upload, plus ownership checks on private QR logos. Gate dashboard access as specified, while allowing authenticated billing/recovery pages to unsubscribed users. Client-only image operations already loaded into a browser cannot be recalled or reliably metered; server gates protect delivery and subsequent requests.

Use Stripe-hosted subscription Checkout, signed idempotent webhooks, a local subscription projection and Customer Portal. Preserve founding subscriptions on their original recurring Price; sell future pricing through separate Prices. Initial schema additions are users, plans, subscriptions, payment_events, tool_usage plus secure sessions and recovery/verification support; details and migration ordering are in the plan. No current database needs replacement.

## 19. Security findings

These are source findings and deployment risks, not results of a penetration test.

| Priority | Finding/evidence | Required treatment |
| --- | --- | --- |
| High | All tool pages and expensive PHP handlers lack auth/subscription checks | Shared server guard including direct legacy paths and POSTs |
| High | QR upload accepts extension-only SVG/raster files and serves them on the application origin (`qrcode/index.php:105`) | Validate content, bound size/pixels, reject or securely sanitize/rasterize SVG, use private owned storage; raw SVG opened directly can execute active content depending on serving policy |
| High | Four-digit hardcoded rate PIN; unlimited attempts (`dtf/builder.php:5`) | Replace with authenticated role/permission checks, CSRF and rate limiting; do not reuse PIN as a credential |
| High | Customer contact JSON and temporary logos have no application authorization | Deny static access; remove or migrate legacy contacts deliberately; authenticated ownership-aware file responses |
| High | Large unauthenticated uploads, image parsing and QR rendering have no rate/resource budget | Enforce request, byte, dimension, pixel, parser and concurrency limits; Imagick policies if enabled |
| Medium | Mutating form/JSON/upload endpoints lack CSRF checks | Session-bound tokens; webhook signature exception only for the Stripe endpoint |
| Medium | QR enables display/startup errors and shows exception text; DTF returns raw exception messages and server limits | Generic production responses, private structured logs without secrets |
| Medium | Public source tree contains manifests, tests, docs, vendor/node_modules and data | Explicit static allowlist; deny nonpublic paths and directory listing; publish only required icon/browser assets |
| Medium | Cleanup runs during requests, including GET; no ownership or scheduled expiry | Private file records and bounded scheduled cleanup |
| Medium | Browser quote totals and PIN-authorized rate can be changed with developer tools | Treat printed quotes as local calculations; independently calculate any future server orders/payments |
| Medium | QR CDN script has no integrity attribute; many inline scripts/styles complicate CSP | Pin/self-host or add integrity and adopt compatible nonce/hash CSP |
| Medium | No app security headers, trusted-host policy or session hardening | Verify proxy/server headers; configure HTTPS, canonical host, cookie policy, cache controls and frame protections |
| Medium | PNG parser reads declared chunk lengths; no explicit dimension budgets; Canvas processing can exhaust memory | Bound metadata reads, validate DPI/dimensions, cap batch/export pixels; preserve normal artwork behavior |

Existing positives: QR text output generally uses HTML escaping, built-in icons use an allowlist, filenames use cryptographic randomness; DTF metadata uploads perform MIME and image checks; builder text uses escaping/textContent in relevant rendering paths. These do not substitute for access control. No application SQL injection surface exists yet because no database calls exist; future SQL must use prepared statements. Dependency vulnerability scanning and deployed HTTP/header testing were not performed.

## 20. Likely modified/added files

| Existing file(s) | Expected future change |
| --- | --- |
| `index.php`, `assets/css/style.css`, `assets/img/*` | Public landing/router, Mber branding; preserve dashboard behavior in new protected view |
| `qrcode/index.php` | Early shared guard/CSRF, private validated logos, safe errors, route URLs and branding |
| `dtf/index.php` | Protected compatibility redirect; leave unreachable legacy functionality alone unless separately authorized |
| `dtf/builder.php` | Early guard, role-based override, CSRF, canonical endpoint/asset URLs and branding |
| `dtf/upload.php` | Guard/CSRF before work, bounded validation, safe JSON errors |
| `dtf/inc/image_info.php` | Bounded metadata parsing/cleanup adjustments where needed |
| `dtf/assets/js/upload-progress.js` | Configured endpoint, CSRF and session-expiration responses |
| `cleanimg/index.php` | Early PHP guard, URLs and Printrivo Image Studio branding; retain algorithms |
| `dtf/customers.json` | Block public exposure; decide archival/import separately |
| `dtf/tests/builder.test.cjs` | Preserve baseline; adapt only for intentional integration changes |
| QR dependency manifests | Prefer leave QR dependencies isolated initially; reconcile only if needed |

Proposed new files: root `composer.json`/lock, `app/bootstrap.php`, `app/routes.php`, auth/session/CSRF/authorization services, billing/webhook services, PDO repositories, SaaS views, migrations, CLI maintenance jobs, integration tests, `.env.example` without secrets, `.gitignore`, and web-server routing/access configuration. Exact names are proposals, not existing structures.

## Verification and limits

All seven first-party PHP files passed `php -l`. `node dtf/tests/builder.test.cjs` passed pricing boundaries, minimum price, override/reset, bounds, invoice blocking, fit, spacing, rotation and piece preservation. This is a VM test, not browser/export coverage. No dependency installs, application execution that creates uploads, database changes or external account operations were performed. `git status` is unavailable because this checkout's `.git` is not a usable repository; application file hashes were recorded for documentation-only verification. Production behavior, browser rendering and billing do not have end-to-end validation yet.
