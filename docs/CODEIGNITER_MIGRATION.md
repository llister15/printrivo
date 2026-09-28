# CodeIgniter migration

> Follow-up: the [sales page and account phase](SALES_AND_ACCOUNTS.md) is now implemented. The earlier statements below about absent authentication/database describe the migration snapshot, not the current app.

Implemented 2026-09-17 at the user's request. This document supersedes the framework,
routing, paths, dependency and session findings in the original architecture audit.
Mber Digital branding and the existing tool algorithms are preserved.

## Current structure

| Component | Location |
| --- | --- |
| Framework / dependency lock | `composer.json`, `composer.lock`, CodeIgniter 4.7.4 |
| Canonical and compatibility routes | `app/Config/Routes.php`; automatic routing disabled |
| Dashboard | `app/Controllers/Dashboard.php`, `app/Views/dashboard.php` |
| Printrivo QR Generator | `app/Controllers/QrCode.php`, `app/Services/QrGenerator.php`, `app/Views/tools/qr.php` |
| DTF builder and JSON handlers | `app/Controllers/GangSheet.php`, `app/Views/tools/gang_sheet.php` |
| Image metadata helpers | `app/Helpers/dtf_image_helper.php`, `dtf_upload_helper.php` |
| Printrivo Image Studio | `app/Controllers/ImageStudio.php`, `app/Views/tools/image_studio.php` |
| Shared theme, logo, JS and SVG icons | `public/assets/` (root Apache rewrite preserves `/assets/` URLs) |
| Public front controller | `public/index.php` |
| Existing Apache mount support | Root `.htaccess`, root `index.php`, small legacy PHP bridges |
| Temporary custom logos | `writable/uploads/qr-logos/` |
| File sessions, logs, cache | `writable/` |
| Dormant calculator and old contact data | `app/Legacy/`, not routed or public |
| Tests | `tests/Feature`, `tests/js`, `tests/integration` |

## Behavior and compatibility

The dashboard and canonical `/tools/qr`, `/tools/gang-sheet`, `/tools/image-studio`
routes use CI views. Old `/qrcode/`, `/qrcode/index.php`, `/dtf/`,
`/dtf/index.php`, `/dtf/builder.php`, `/dtf/upload.php`, `/cleanimg/` and
`/cleanimg/index.php` remain supported. All share CI routing and filters. Directory
DTF landing pages redirect to the canonical builder. Old physical PHP files are
framework bridges, not copies of the old applications.

New DTF POST endpoints are `/api/tools/gang-sheet/inspect` and
`/api/tools/gang-sheet/rate`; legacy POST aliases use the same controllers and JSON
contracts. File inspection still uses the existing DPI parsing and browser PNG
fast path. QR rendering retains the exact 6.0.0 generator dependency and options;
Composer now manages it at the root. QR JavaScript composition/export and Image
Studio image-processing JavaScript are unchanged. The builder JavaScript only
changes request URLs and CSRF headers; its arrangement, pricing and export remain.

URLs use CI helpers, including assets, custom logos, form actions and dashboard
links. The application supports a configured subdirectory base URL. Existing old
static node_modules/vendor URLs are not public dependency trees; the SVG icons and
canvas2svg browser library are published as explicit assets with their licenses.
No database was created or migrated.

## Request and file safety

CI's CSRF filter covers every browser POST route. Tokens are sent by the QR form
and by DTF requests. Cookie-based CSRF with regeneration disabled allows multiple
open tools and sequential AJAX uploads; expiry remains two hours. Failures return
403 instead of redirecting a rejected upload to an HTML page. DTF JSON calls retain
useful error responses. Authenticated sessions will require a policy review when
account authentication is added.

Temporary QR logos are session-scoped and served by a controller, not a public
upload directory. Anonymous file sessions are used solely for ownership here;
they are not user login sessions. Logo uploads have a 10 MB limit; raster logos
must have valid MIME/image content and at most 40 megapixels. SVG uploads are
sanitized with `enshrined/svg-sanitize` 1.0.0 and remote references removed. Served
logos use private/no-store caching, nosniff and a restrictive SVG-compatible CSP.
Expired logos cannot be fetched; one-hour request-triggered cleanup remains.
A scheduled cleanup task is still future operational work.

The old DTF PIN now comes from `.env` rather than tracked source. Its local value
was preserved, with no public default for new installations. It remains a legacy
compatibility mechanism pending account roles; throttling and subscriber gating
are not implemented by this framework migration. Image resource budgets and other
security items from the original audit remain follow-up work where not described
above.

## SaaS follow-up architecture

Use CI filters for authentication and eligible-subscription checks on canonical
and legacy routes together. Use CI configuration, sessions, validation, database
connections, models and migrations rather than the previously proposed standalone
PHP bootstrap/router/PDO layer. Evaluate CodeIgniter Shield during the account
authentication phase, before choosing the final user/session schema. Do not combine
its schema blindly with the earlier proposed tables. Stripe Checkout/webhook/portal
business rules and founding-price preservation remain as specified.

No authentication, paid gating, account pages, Stripe resources or billing tables
were added. Routes must not be described as subscriber-protected until those
phases are implemented and tested.

## Validation

- PHP syntax checks on application, config, views, helpers and entry points.
- PHPUnit route, legacy alias, CSRF rejection, private route and QR render checks.
- Existing gang-sheet VM regression checks, now loading the CI view.
- Local HTTP tests for pages, assets, redirects, QR output, multipart metadata,
  method errors, CSRF, sanitized SVG logos and cross-session denial.
- Headless Chrome rendering of dashboard and builder; tool pixels/layout retained.
- The HTTP suite also passed through the existing Apache `/tools/` mount, including
  subdirectory URLs, asset serving and private-file denial.

See the root README for setup and test commands. Production hosting changes,
SSL configuration and deployment remain separate from local migration.
