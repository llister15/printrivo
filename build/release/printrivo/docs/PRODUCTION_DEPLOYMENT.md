# Printrivo production deployment

This release is packaged for deployment, not certified as live-payment ready.
No private .env, database, accounts, uploads, sessions, logs, or development
PHP dependencies are included. Runtime dependencies are included in vendor/.

## Hosting and installation

1. Use PHP 8.2 or newer compatible with composer.lock, MySQL/MariaDB, HTTPS,
   and Apache with mod_rewrite (or equivalent Nginx front-controller routing).
   Run `composer check-platform-reqs --no-dev` to verify PHP extensions.
2. Extract the printrivo folder outside the public web directory. Set the domain
   document root to its `public/` directory. Never expose the project root,
   app/, vendor/, writable/, or .env. On shared hosting, ask the host to set
   this document root; do not upload the entire project into public_html.
3. Copy .env.example to .env privately. Set the real HTTPS app.baseURL with a
   trailing slash, production environment, database credentials, secure cookies,
   and forced HTTPS. Configure trusted reverse proxies only if your host uses one.
4. Create a NEW production database and database user. Do not copy the sandbox
   database or seed test users/subscriptions. Back up any existing live database
   before applying migrations. Grant the web process write access to writable/
   only; do not use permissions 777. Restrict .env to the deployment/web account.
5. From the project directory run:

   ```sh
   composer check-platform-reqs --no-dev
   php spark migrate --all
   php spark paypal:check
   ```

## Optional analytics

See `docs/GOOGLE_ANALYTICS.md` to configure GA4. Tracking is disabled until a
valid Measurement ID is configured and each visitor accepts analytics. Disable
Enhanced measurement in the GA4 stream before enabling this integration.

## Owner admin access

Register your owner account, then run `php spark admin:grant YOUR_ACCOUNT_EMAIL`
from the application directory on the server. Visit `/admin` after logging in.
The new migration creates the admin cancellation audit table. See
`docs/ADMIN_DASHBOARD.md` for counts, access controls, and cancellation behavior.

## Required live configuration

- PayPal: use LIVE app credentials, a live webhook ID, and four distinct live
  USD subscription plan IDs (standard/Alpha, each with and without a trial).
  Standard is $9.99/month; Alpha is $4.99/month. Trial plans must have a 14-day
  free period followed by monthly billing. Follow docs/PAYPAL_SETUP.md for
  plan validation and subscribed webhook events. The setup-trials command is
  sandbox-only. Never reuse sandbox resources for live checkout.
- Generate a new stable PayPal.trialHashKey for this production database:
  `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`
  Store it privately and back it up. Do not rotate it casually.
- Set PayPal.enabled=true only after configuring and validating the above.
  The supplied template keeps billing disabled by default.
- Register reCAPTCHA v3 for the production hostname, configure its site/secret
  keys and hostname, and enable it. See docs/RECAPTCHA_SETUP.md.
- Configure the web host's SMTP credentials and verified sender privately.
  Use info@mberdigital.com as the bug report recipient. See docs/BUG_REPORTS.md.
  Verify password-reset and bug-report delivery, including spam folders.
- Rotate the sandbox credentials previously present in the development
  .env.example. They have been removed from this release and the source template.

## Acceptance before opening signup

Use a separate HTTPS staging site/database with sandbox credentials first.
Complete real PayPal buyer approval, trial start, cancellation, payment failure,
renewal and webhook delivery tests described in PAYPAL_SETUP.md. Automated tests
alone do not verify provider delivery or the 14-day billing schedule.

On the live host check home, pricing, registration, login/logout, password reset,
Terms, Privacy and 404 pages. Verify all GIFs and each tool with an eligible account.
Verify logged-out and unsubscribed accounts cannot open tool URLs directly.
Verify payment setup selects the right standard/Alpha plan and displays explicit
recurring terms. Verify HTTPS webhook delivery and signature verification.
Do not enable paid customer traffic until these checks pass.

Check .env and private directories cannot be retrieved over HTTP. Confirm
production errors do not expose debug output. Keep database and uploaded-file
backups outside public/. Monitor application logs and PayPal webhook failures.
Review the business's Terms and Privacy text for your launch jurisdiction and
actual hosting/data practices before accepting customers.

## Rollback

Retain the previous release and a database backup. For an update, use maintenance
mode or host-level traffic control while migrating, then switch the document
root atomically if supported. If rollback is needed, restore the compatible code
and database together; do not blindly reverse migrations on live billing data.
Preserve/reconcile PayPal events and transactions received during an outage.
