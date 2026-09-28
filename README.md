# Printrivo

Professional print-production tools from Mber Digital, running on **CodeIgniter 4.7.4**.

## Setup

Requires PHP 8.2+ with intl, mbstring, GD, fileinfo and SQLite3 (for the default database), and Composer 2.

```sh
composer install
cp .env.example .env
php spark migrate --all
```

Set `app.baseURL` to the externally visible URL, with its trailing slash. The existing
local XAMPP checkout is configured for `http://localhost/tools/`. For the development
server, use `http://localhost:8080/`, then run:

```sh
php spark serve
```

Point the production web-server document root at `public/`. Only `writable/` needs
application write access (logs, sessions, temporary QR logos and cache). Enable HTTPS
and set the secure-cookie and HTTPS options shown in `.env.example` in production.
Do not serve `app/`, `vendor/`, `writable/`, tests, docs or environment files.

The root `.htaccess` and `index.php` support the existing Apache subdirectory setup
without changing the system virtual host. This requires Apache 2.4 rewrite support
and AllowOverride. Other servers should use `public/` as their document root and
route non-file requests to `public/index.php`; the root compatibility rules are
Apache-specific. Do not use the repository root with a generic static server.

## Tools

- `/tools/qr` — Printrivo QR Generator
- `/tools/gang-sheet` — Printrivo Gang Sheet Builder
- `/tools/image-studio` — Printrivo Image Studio
- `/` — public sales page
- `/pricing` — Founding Supporter offer
- `/register`, `/login` — member account signup and login
- `/dashboard` — authenticated tools dashboard
- `/account/subscription` — authenticated subscription status

Legacy QR, DTF and image-cleaner page URLs remain framework routes. All paths above
are relative to `app.baseURL` (for example `/tools/tools/qr` on the XAMPP mount).
Assets live in `public/assets/`, with logos in `public/assets/img/`. The root
Apache rewrite preserves `/assets/` URLs without a filesystem shortcut.

CodeIgniter Shield provides account registration, email/password login and POST-only
logout. Session CSRF protects forms and API calls; auth POSTs are rate limited.
Configure the database in `.env` before running `php spark migrate --all`.
Keep database backups private. Do not recreate or overwrite an existing `.env`
during deployment.

Visitors land on the sales page. The dashboard requires login, and every canonical
and legacy tool route additionally requires a locally recorded active subscription
with a future paid-through date. Registration never grants paid access. PayPal
subscription checkout is implemented, sandbox first; Stripe remains deferred.
Configure PayPal in `.env` and run `php spark paypal:check` before sandbox
acceptance testing. Test entitlements require the explicit development seeder.

Email verification and password recovery are not enabled until outbound mail and
those flows are configured. Accounts use Shield identities for email/password hashes;
first/last names are added to users by the application migration. The debug toolbar
is disabled to avoid collecting customer credentials or session data.

The gang sheet builder focuses on layout and PNG export. Sheet details show
used/remaining rectangle area, coverage, dimensions, piece count and export size.
Center artwork and Trim sheet complement Auto Build, duplicate, rotate and resize.
Pricing, rate overrides, quotes and invoices have been removed, including the rate API.

## Verification

Web and CLI database connections use the project `.env` settings. There is no
implicit SQLite fallback; required host, database and driver settings must be
present. PHPUnit alone uses its isolated in-memory test database.
Run `php spark db:target` to check the connected database, table prefix, admin
presence and entitlement without printing credentials. Shield stores login
emails in `po_auth_identities.secret`, linked through `user_id` to `po_users.id`
when using the `po_` prefix.

To unlock tools for `admin@printrivo.test` locally, run
`php spark db:seed TestSubscriptionSeeder` after creating the admin. This grants
30 days of synthetic test access without a payment. Re-running renews the same
test entitlement; PayPal records are preserved. Both creation and access are
restricted to development/testing. Run this command in the installation/database
you actually use (for example, your MySQL installation).

For a local test administrator, set `CI_ENVIRONMENT = development` and
`PRINTRIVO_TEST_ADMIN_PASSWORD` (at least 12 characters) in your local `.env`,
then run `php spark migrate --all`. The `CreateDefaultTestAdmin` migration
creates `admin@printrivo.test` using that password after Shield creates its
authentication tables. Use `--all` to include Shield, including on MySQL with
a table prefix. Production or an unset password skips account creation.
If this migration already ran without a password, run
`php spark db:seed TestAdminSeeder` after setting it.
Rollback preserves the account and its history.
The seeder only runs in development/testing, and re-running it preserves the
existing admin's password and subscription. Remove the password setting after
creation. No credentials are shipped in source control. This account uses the
normal login and dashboard; the admin role does not bypass subscription checks.
Complete PayPal sandbox payment to test protected tools (see
[PayPal setup](docs/PAYPAL_SETUP.md)).

```sh
composer test
node tests/js/builder.test.cjs
php spark routes
```

Against a disposable running instance with its correct `app.baseURL`:

```sh
python3 tests/integration/account_smoke.py http://localhost:8080/ writable/mber.sqlite
# With MBER_TEST_EMAIL and MBER_TEST_PASSWORD for an active disposable test subscriber:
python3 tests/integration/http_smoke.py http://localhost:8080/
```

The account suite creates and deletes one local disposable user, checking signup,
login/logout, CSRF, dashboard protection and unpaid-tool denial. The tool HTTP suite submits QR content and temporary SVG logos, tests multipart metadata
uploads, checks both legacy and canonical pages, verifies CSRF and logo session
isolation, and checks private-path denial. Do not run against production.

See [migration details](docs/CODEIGNITER_MIGRATION.md) and the
[SaaS implementation plan](docs/SAAS_IMPLEMENTATION_PLAN.md).

See [sales page and account implementation](docs/SALES_AND_ACCOUNTS.md) for this phase.


## Current pricing and print widths

The standard plan is $9.99/month. Alpha is an optional registration code for the
$4.99/month early-adopter offer while continuously subscribed. It is validated
case-insensitively and stored as `users.promo_code = ALPHA`; it does not create a
subscription. Apply the saved offer when implementing Stripe checkout.
Run `php spark migrate --all` to add the nullable promotion field on existing installs.

The gang sheet builder supports 16-inch and 22-inch widths. Switching widths
automatically arranges artwork while preserving image content and dimensions.
Adding artwork or changing spacing also arranges it and fits the sheet length;
sheet length is also editable. Oversized artwork
must be resized using the inputs before export. Uploaded backgrounds and margins
are preserved; the builder does not remove backgrounds or crop artwork.
Canvas size, coverage and 300 DPI PNG exports use the selected width.


## Mobile tools

QR Generator and Image Studio stack controls and previews on small screens.
The gang sheet builder provides Setup, Canvas, and Edit & Details navigation
below 761px. The canvas scales to the phone width while retaining physical
measurements and full 300 DPI export dimensions. Touch dragging/resizing and
numeric artwork controls are supported. Large image batches and long 300 DPI
sheets still depend on the device's available browser memory.

## Single-image Image Studio

Image Studio edits one PNG, JPG, or WebP at a time, entirely in the browser.
Opening a file preserves its original background and margins. Users explicitly
apply edge-connected background removal, transparent-margin trimming, numeric
cropping, 2×/4× smooth upscaling, proportional print sizing, rotation, flipping,
brightness/contrast/saturation, and color replacement. Text layers support font,
size, color, weight, position inputs, and canvas dragging. Undo/redo retains a
bounded in-memory edit history; replacing an edited image asks for confirmation.
Rotate and flip flatten text, with undo restoring the editable layers.

Exports are RGB PNGs with the selected 150/300/600 DPI metadata. Preview
backgrounds and selection outlines are excluded. Upscaling resamples pixels;
it does not reconstruct missing detail using AI. Limits: 40 MB input, 24 million
pixels, 12,000 pixels per side. Edits and history are not saved across reloads.

Browser regression tests require Puppeteer and a local Chrome installation:
`node tests/js/studio.browser.cjs` (set `CHROME_PATH` if needed). They exercise
image preservation, background removal, cropping, trim, resizing, color edits,
text, undo/redo, PNG metadata, and the mobile layout without a live account.
