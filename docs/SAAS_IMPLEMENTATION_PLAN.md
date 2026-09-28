# Printrivo SaaS implementation plan

> Progress update: [sales pages, Shield account signup/login and dashboard protection](SALES_AND_ACCOUNTS.md) are implemented. Stripe checkout remains deferred by the user. Earlier phase descriptions are the planning baseline; the linked implementation notes define current behavior.

Status: **SaaS phases remain planned; CodeIgniter framework migration is implemented**. Date: 2026-09-17.

The user subsequently requested a CodeIgniter conversion. [Migration details](CODEIGNITER_MIGRATION.md) supersede the standalone PHP/router/PDO recommendations below: use CodeIgniter controllers, filters, configuration, models and migrations. Routing, CSRF and temporary-logo sessions now exist; account authentication and billing do not. Reconcile the user schema with the authentication package selected in that phase.

Based on the completely read, saved `AGENTS.md` and `docs/PRODUCT_SPEC.md`, and [the existing architecture audit](EXISTING_ARCHITECTURE.md). Preserve the Printrivo QR Generator, Printrivo Gang Sheet Builder and Printrivo Image Studio. Add SaaS infrastructure incrementally around the current PHP/JavaScript application.

## Product decisions already established

Mber Digital owns Printrivo. Launch includes all three tools for **$4.99/month**, under the Founding Supporter offer. Subscribers keep that price while continuously subscribed. Future new-customer prices are $9.99/month and $19.99/month, using separate Stripe Prices without migrating existing subscribers automatically. Currency should be confirmed before creating live Prices; USD is a provisional assumption, not established by the dollar symbol alone.

The public headline is “Professional Print Tools. One Simple Subscription.” The initial CTA is “Get Started for $4.99/mo”. Present the supplied print-production message, professional responsive design and Mber Digital footer. Do not claim an unconfigured offer deadline. Do not replace the tools' algorithms or reorganize their code for aesthetic reasons.

## Architecture decision

Keep PHP; introduce a shared bootstrap, explicit method/path router, small services and PDO repositories. There is no existing framework or database layer to reuse. A wholesale framework migration adds risk without helping preserve these tools. Propose MySQL/MariaDB with transactional tables and UTF-8 via PDO, subject to deployment confirmation; local PDO MySQL availability is not proof of a provisioned database.

Suggested structure (future additions):

```text
app/bootstrap.php              Environment, errors, DB and session setup
app/routes.php                 Explicit method/path and access policy map
app/auth/                      Authentication, recovery, CSRF, role checks
app/billing/                   Checkout, portal, webhook synchronization
app/repositories/              Prepared SQL and ownership-scoped queries
app/views/                     Marketing/auth/dashboard/account views
database/migrations/           Versioned schema changes
bin/                           Migrations, retention, billing reconciliation
tests/                         Auth/access/billing integration coverage
```

Keep existing tools in place initially with small PHP guards/adapters. Application internals, secrets and private storage must be outside the served tree or explicitly denied at the web server. A later public-directory packaging change is optional, not a prerequisite for rewriting tools.

### Route and compatibility design

| Routes | Policy |
| --- | --- |
| GET `/`, `/pricing`, `/terms`, `/privacy` | Public |
| `/register`, `/login`, `/forgot-password`, `/reset-password` | Public form flows; state changes POST with CSRF/rate limits |
| Verification callback/resend routes | Signed or hashed expiring token; resend requires appropriate authenticated policy |
| POST `/logout` | Authenticated + CSRF |
| GET `/dashboard` | Authenticated + eligible subscription, showing tools and subscription status |
| GET `/account`, `/account/subscription`, `/account/billing` | Authenticated even without an eligible subscription |
| GET/POST `/tools/qr` | Authenticated + eligible; POST CSRF |
| GET `/tools/gang-sheet` | Authenticated + eligible |
| GET `/tools/image-studio` | Authenticated + eligible |
| POST `/api/tools/gang-sheet/inspect` | Authenticated + eligible + CSRF, image validation |
| POST `/api/tools/gang-sheet/rate` | Authenticated + eligible + explicit rate-override permission + CSRF |
| POST `/billing/checkout`, `/billing/portal` | Authenticated + CSRF; do not require existing paid access |
| GET `/billing/success`, `/billing/cancel` | Authenticated; success shows synchronization status, never grants access |
| POST `/webhooks/stripe` | No browser session requirement; raw-body signature verification |
| GET `/files/qr-logo/{id}` | Authenticated + eligible + owner check; no arbitrary filesystem path |

Map friendly tool routes to current implementations. Use configured application-base URLs for forms, upload/rate calls, scripts and images; do not rely on relative URLs surviving a new route depth. Preserve the current response shapes consumed by JavaScript.

Legacy `/qrcode/index.php`, `/dtf/index.php`, `/dtf/builder.php`, `/dtf/upload.php` and `/cleanimg/index.php` must run the same early guards. Directory-index aliases are equally covered. GET aliases may redirect after authorization; POST aliases should dispatch compatibly through the guard rather than redirect and lose their bodies. Deny unrecognized methods. Account recovery and billing must remain reachable after access expires.

Use explicit static asset allowlists. Block `dtf/customers.json`, QR raw upload paths, dependency source, docs and configuration. Move QR logos to private storage and serve only validated owned assets. Guarding PHP pages cannot protect public static uploads.

**Limit:** once a subscriber has loaded browser code and local artwork, server authorization cannot stop its offline Canvas processing or count its exports reliably. Do not promise immediate revocation inside already loaded pages or reliable per-operation billing. Protect page delivery and every subsequent server request. A future server-processing requirement would be a separate scope change.

## Phase 0 — Confirm deployment and acceptance baseline

1. Confirm web PHP version/extensions, domain/base path, web-server rewrite controls, database engine, SMTP delivery, scheduled-job support and private storage path.
2. Confirm currency, whether email verification is mandatory before Checkout/tool access, and business treatment of payment retries, grace periods, refunds, disputes and interrupted founding subscriptions.
3. Capture representative QR/logo, DTF layout/quote/export and cleaner background/upscale results in a test environment. Keep the existing DTF VM tests.
4. Decide who may override print-job rates; do not turn every subscriber into an administrator. Confirm disposition of legacy contact JSON.

Exit: deployment and policy decisions recorded, normal tool outputs baselined. No accounts or contacts silently migrated.

## Phase 1 — Shared foundation, routing and migrations

Add root Composer management for new SaaS dependencies (including `stripe/stripe-php` and a maintained mailer when implemented), without unnecessarily moving the working QR autoloader. Check dependency compatibility and pin versions in lockfiles. Select the supported stable Stripe SDK/API at implementation time; do not hardcode a guessed latest version in this plan.

Add environment validation, trusted canonical URL, production errors/logging, DB connection, migration runner, explicit router and protected configuration paths. Start with no real secrets in tracked files. New configuration contract:

| Proposed variable(s) | Purpose |
| --- | --- |
| `APP_ENV`, `APP_URL`, `APP_BASE_PATH` | Environment and canonical routes/links |
| `DB_DSN`, `DB_USER`, `DB_PASSWORD` | PDO connection |
| `SESSION_NAME`, `SESSION_TTL_SECONDS`, `SESSION_IDLE_SECONDS` | Session identity and expiry; secure flags enforced in production |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_ENCRYPTION` | Verification/reset delivery |
| `STRIPE_API_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_API_VERSION` | Backend billing credentials/version |
| `STRIPE_PORTAL_CONFIGURATION_ID` | Optional controlled portal configuration |
| `PRIVATE_STORAGE_PATH`, `UPLOAD_MAX_BYTES`, `UPLOAD_MAX_PIXELS` | Private logo storage and resource bounds |

Price IDs live in the plans table with controlled environment-specific seed/configuration, not browser-submitted prices. No publishable key is necessary for a simple server-created hosted Checkout redirect. Use environment variables for secrets as required by `AGENTS.md`, populated securely by deployment, never committed or logged. Prefer a restricted Stripe key with permissions tested for required API operations.

Exit: migrations reproducible on an empty test DB; routes resolve at root and configured mount path; protected internals cannot be fetched; tools retain baseline behavior in the test environment. Before exposing the SaaS publicly, finish authentication and access protection.

### Required database migrations

There is no existing database schema to alter. Use ordered migrations, foreign keys, prepared statements, UTC timestamps, explicit indexes, transactional writes and backup/restore procedures.

| Order/table | Fields and constraints |
| --- | --- |
| 001 `users` | Required spec fields: id, first_name, last_name, normalized unique email, password_hash (room for algorithm changes), email_verified_at nullable, status, created_at, updated_at. Add explicit role/permission support and session/auth version for revocation. No default admin privileges. |
| 002 `sessions` | Hashed opaque session identifier, nullable user_id for anonymous CSRF sessions, protected session data, created_at, last_seen_at, expires_at; indexes on user and expiry. Transaction-safe session handler and rotation. |
| 003 recovery/verification tokens | `password_reset_tokens`, `email_verification_tokens`: user_id, unique token_hash, expires_at, consumed_at, created_at. Atomic single-use consumption; never store raw bearer tokens. |
| 004 `plans` | id, name, unique slug, `price` stored as integer minor units (499/999/1999), currency, billing_interval, provider_price_id, is_available, created_at, updated_at. Unique provider price within environment/provider; immutable commercial generation mapping. |
| 005 `billing_customers` | user_id FK, provider, provider_customer_id, environment/livemode; unique user/provider/environment and provider customer ID. Preserves customer mapping before/after a subscription exists. |
| 006 `subscriptions` | Spec fields: id, user_id, provider, provider_customer_id, provider_subscription_id, provider_price_id, plan_id, status, current_period_start/end, cancel_at_period_end, created_at, updated_at. Add canceled_at/ended_at and last_synced_at. Unique provider subscription ID per environment; index user/status. Retain history and original price mapping. |
| 007 `payment_events` | id, provider, event_id, event_type, payload, processed_at; unique provider/environment/event_id. Add received_at, status, attempts, next_retry_at, sanitized last_error. Limit access/retention of payloads containing personal data; never log entire payloads. |
| 008 `tool_usage` | id, user_id, tool, action, created_at; index user/date and tool/date. Allowlisted values; no artwork/content in logs. Distinguish page loads/server actions from untrusted client export telemetry. |
| 009 `uploads` | Required for private QR logos: opaque id, user_id, private storage key, validated MIME, byte/pixel dimensions, created_at, expires_at; owner/expiry indexes. No executable paths or trusted original names. |
| 010 operational records | Expiring rate-limit buckets, security audit events without secrets, and billing operation/idempotency records for safe Checkout retries and concurrency. Can share a suitable durable backend if deployment provides one. |

Add subscriptions only after referenced users/plans exist. Seed only the current available offer after test/live Stripe Price IDs are verified; future generations remain unavailable until intentionally released. Do not invent actual provider IDs. When the founding offer closes, mark unavailable for new signup without deleting its row or altering existing subscriptions. Subscription price snapshots/history must outlive catalogue availability.

No migrations for image projects, saved gang sheets, server-side AI jobs, invoices or team memberships are required for this scope. The legacy contacts file must not become shared cross-user SaaS data. Configure retention and deletion rules before launch; do not equate a user's deletion with deleting legally retained billing records without policy review.

## Phase 2 — Authentication and sessions

Implement registration, login/logout, password recovery/reset and email verification with server validation, generic recovery responses and throttling. Use PHP `password_hash`/`password_verify` with an appropriate supported algorithm and rehash policy. Issue cryptographically random single-use recovery tokens and hash stored values.

Use secure server-side sessions with HttpOnly, Secure in production, SameSite=Lax, host-only cookie and application-wide path. Enable strict session handling; regenerate IDs on login/privilege changes, invalidate old IDs, enforce idle/absolute expiry and revoke sessions after password reset or account disablement. Never hold a session lock during expensive image parsing or remote Stripe calls. Use CSRF tokens on all state-changing browser requests, including logout, uploads and Checkout; allow only validated local return paths.

Exit: registration, login, logout, verification, expiry, reset replay/expiry, fixation resistance, account disablement and CSRF tests pass. Logged-out requests cannot obtain tool HTML or use existing endpoints. Reset/verification emails work in the test environment.

## Phase 3 — Stripe Billing and subscription authorization

Use Stripe Billing with **hosted Checkout in subscription mode**, created by the PHP backend from a server-allowlisted plan. Associate the Customer with the authenticated user, never a browser-selected customer ID. Use stable idempotency keys per billing operation and serialize concurrent signup attempts; reuse pending valid sessions when appropriate. Direct existing subscribers to billing management to prevent duplicate subscriptions. [Stripe Checkout reference](https://docs.stripe.com/api/checkout/sessions).

Founding, V2 and V3 must have separate recurring Price IDs. Represent them as immutable offer generations; a Product per named offer is reasonable if naming them separately, while multiple Prices on a shared suite Product are also appropriate for generations of the same offering. The invariant is preserving each existing subscription's original Price. Do not enable portal plan changes that silently move founding subscribers to a higher generation. When a subscription truly ends, a later new signup uses the then-available offer; undoing a scheduled cancellation before it ends should retain the original price. Decide retry/grace semantics explicitly before enforcement. [Stripe price management](https://docs.stripe.com/products-prices/manage-prices).

The success page reads local synchronized status and can show “payment processing”; it cannot activate an account based on URL parameters. Stripe webhooks are authoritative for synchronization. Implement the required events: `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`, plus `customer.subscription.created` and delayed-payment success/failure events if relevant methods are enabled. Treat completion as a synchronization trigger, not automatic proof of eligibility. [Subscription webhook guidance](https://docs.stripe.com/billing/subscriptions/webhooks).

Verify the unmodified body with `Stripe-Signature` and the endpoint secret before saving/processing events. Enforce a unique event ID and durable status/retry handling. Return 2xx only after durable receipt (or completed short processing); a worker/cron must retry accepted pending events. Invalid signatures fail; transient receipt failures return retryable errors. Handle duplicate and out-of-order events: serialize reconciliation by subscription, retrieve current provider state where needed, and atomically update the subscription projection and processed marker. Do not let an older event reopen an ended subscription. Add scheduled reconciliation and alerting for lag. [Stripe webhook delivery/security](https://docs.stripe.com/webhooks).

Create short-lived Customer Portal sessions server-side only for the authenticated user's mapped Customer, with a configured local return URL. Enable payment-method updates and cancellations; verify portal behavior against grandfathered pricing. [Customer Portal integration](https://docs.stripe.com/customer-management/integrate-customer-portal).

Review applicable tax setup before live billing. Do not enable automatic tax based only on a flag; confirm registrations and required collection settings first. Currency, tax treatment and subscription terms require launch decisions.

### Proposed access policy (business edge cases still require confirmation)

| Local verified condition | Tool access |
| --- | --- |
| Anonymous | Redirect HTML to login; JSON 401 |
| Disabled user | Deny regardless of payment status |
| Active, current eligible subscription | Allow |
| Active with cancel_at_period_end, before paid period ends | Allow until paid period ends |
| No subscription, incomplete, incomplete_expired, canceled, unpaid or paused | Deny; billing/recovery remain available |
| past_due | Provisional default: deny; any grace period must be explicit and tested |
| trialing | Deny by default because no trial is specified; change only if trials are deliberately introduced |
| Paid-through date reached with stale local status | Reconcile; no indefinite access from stale “active” state |
| Billing database unavailable | Fail closed for new protected server requests; preserve public/recovery error responses |

`invoice.payment_failed` is a signal to reconcile, not an instruction to blindly overwrite status independent of current provider state. Recovered renewal can restore access. Founding price retention and temporary access suspension are separate decisions; avoid automatically changing Prices during dunning/retry handling. Do not use catalogue `is_available` as existing subscriber eligibility.

Exit: sandbox signup, successful renewal, failed payment, recovery, cancellation, period-end expiry, bad signature, duplicate/concurrent/out-of-order events, webhook retry and price-generation retention tests pass. No test/live IDs mix.

## Phase 4 — Wrap and secure all existing tools

Add the early shared user/subscription guard to every physical entry point and canonical adapter. Attach CSRF tokens to QR form, DTF metadata XHR and rate POST; return JSON 401/403 errors for API clients. Replace the PIN with an explicit permission while preserving authorized override behavior. Do not send administrators' credentials to JavaScript.

Validate custom logos by content, byte/pixel budgets and decode capability. Preserve safe SVG logo support using a reviewed sanitization/rasterization strategy; explain and test any compatibility impact before changing accepted formats. Store private owned files and serve with correct content type/nosniff and safe active-content policy. Harden DTF parsers and bound image resources without silently shrinking normal exports. Schedule owned-file expiry rather than scanning every user's uploads on a request.

Keep local cleanup, upscaling, QR composition, gang sheet arrangement and print pricing intact. Add modest authenticated navigation/Mber branding and configured URLs. Rebranding the tool chrome is not authorization to redesign the editors. Track server-confirmed tool entry/use without collecting artwork or pretending browser telemetry is authoritative usage billing.

Exit: direct legacy and friendly routes fail closed for visitors/unsubscribed users; active subscribers retain QR generation, custom logos, PNG/SVG downloads, metadata inspection, DTF arrangement/printing/export and image cleanup/upscale. Cross-user logo access fails. No protected responses enter shared caches. Browser tests cover session expiry during an upload and subscription change between page load and server action.

## Phase 5 — Public site, dashboard and account experience

Create all required marketing, pricing, registration/recovery and legal pages. Move the internal tool-list role into `/dashboard` with the specified three tool cards and subscription status. Add `/account`, `/account/subscription` and `/account/billing`; unavailable subscriptions link to Checkout or Portal. Preserve billing access for canceled/past-due users. Display actual synchronized status and paid-through date rather than promising activation before webhooks arrive.

Apply the required headline, offer explanation, brand/footer, responsive layout and current available price. Keep public price, database plan and Checkout selection consistent when introducing a future generation. Legal text requires business review rather than invented terms.

Exit: new-user and returning-user funnels match the product specification; form errors, keyboard navigation, mobile layout and pending-payment states are usable.

## Phase 6 — Regression, operations and launch readiness

Run existing PHP lint and DTF baseline tests plus meaningful HTTP/browser integration tests. Required matrix: registration, login/logout, reset/verification, anonymous direct access, no subscription, active, canceled now/at period end, past due, failed/recovered renewal, signature failure, duplicate/out-of-order events, and all three tool workflows. Add tests for canonical and legacy POSTs, CSRF, private-file ownership, malicious/oversized uploads, role override checks and founding Price continuity across V2/V3 releases.

Verify production HTTPS/security headers, session cookies, trusted proxies, resource limits, logging redaction, database backups/restore, migration rollback strategy, scheduler execution, webhook lag alerting and secret rotation procedures. Scan dependencies before deployment. Use a compatible CSP for inline code and necessary images/Blob URLs; do not break Canvas/export functionality with an untested blanket policy.

Deploy migrations before dependent code, back up first, and roll out in staging before production. Rollback must never reopen legacy tools without access checks or erase already received billing events. Keep billing events durably replayable and document manual reconciliation. Activation of live billing and production deployment are future actions, not performed by this audit.

Exit: acceptance matrix passes, operational ownership is established and launch is explicitly authorized.

## Principal integration risks

- Friendly URLs can break relative assets, forms and XHR; test both path styles and mount points.
- Early output breaks PHP sessions/redirects; bootstrap must run before HTML and tool side effects.
- Private logo storage changes preview/export URLs; regression-test image loading and SVG behavior.
- Guards must run before QR directory cleanup and DTF upload parsing, not just before rendering buttons.
- Restricting Portal options and preserving Price mappings is essential to the founding promise.
- Inline script extraction in the existing VM test can be affected by added scripts; preserve its meaning rather than disabling it.
- Client-side tools cannot be forcibly revoked after delivery; per-export metering would require a separate architecture decision.

Further SaaS phase implementation remains subject to the user’s next instruction. The file modification inventory is in the architecture audit; each future phase should identify its concrete affected files and explain any tool compatibility risk before edits.

## Reference verification

Stripe guidance was checked against official documentation on 2026-09-17 using the Stripe best-practices/documentation skills. The `stripe` CLI is not installed locally, so official web documentation was used as the fallback. No Stripe resources, subscriptions, keys or accounts were created or changed.
