# PayPal sandbox billing

Implemented September 18, 2026. PayPal is disabled by default. No PayPal
credentials or remote products/plans were created in this implementation.
Stripe remains deferred.

## Configuration

Run migrations on each deployment:

```sh
php spark migrate --all
```

The local database migration has already been applied. It adds checkout retry
records, payment-event deduplication, provider environment and payment metadata,
and the account's Alpha redemption timestamp.

Configure the existing private .env entries using a PayPal sandbox application's
credentials and resources from the same merchant account:

```ini
PayPal.enabled = true
PayPal.environment = sandbox
PayPal.clientId = YOUR_SANDBOX_CLIENT_ID
PayPal.secret = YOUR_SANDBOX_SECRET
PayPal.webhookId = YOUR_SANDBOX_WEBHOOK_ID
PayPal.standardPlanId = P-YOUR_STANDARD_PLAN
PayPal.alphaPlanId = P-YOUR_ALPHA_PLAN
PayPal.currency = USD
```

Run `php spark paypal:check` to identify missing settings and display the webhook
endpoint without printing secrets. Configuration readiness does not verify
remote credentials, plans, or successful delivery; complete the acceptance tests
below with a sandbox buyer before launch.

Do not put credentials in templates, JavaScript, source control, or chat.
Use a separate sandbox buyer account for approval.

The two active subscription plans must each contain exactly one REGULAR billing
cycle: MONTH, interval_count 1, total_cycles 0 (ongoing). Fixed prices must be
9.99 USD and 4.99 USD respectively. These no-trial plans are retained for returning members. No setup fee or additional plan tax is supported. Use a digital-service product and
quantity 1. The server checks plan terms before creating a subscription.

The saved Alpha code selects the discounted plan server-side. The public price
stays $9.99. The discounted amount appears on the account page for eligible
accounts and on PayPal approval. A completed Alpha payment consumes eligibility
for a replacement discounted subscription; ongoing renewals keep the same plan.

## Webhook

Set app.baseURL to the externally reachable HTTPS application URL. Register
its /webhooks/paypal endpoint on that same sandbox REST application. Include any
deployment subdirectory, e.g. https://test.example.com/tools/webhooks/paypal.

Subscribe to:

- BILLING.SUBSCRIPTION.CREATED
- BILLING.SUBSCRIPTION.ACTIVATED
- BILLING.SUBSCRIPTION.UPDATED
- BILLING.SUBSCRIPTION.CANCELLED
- BILLING.SUBSCRIPTION.SUSPENDED
- BILLING.SUBSCRIPTION.EXPIRED
- BILLING.SUBSCRIPTION.PAYMENT.FAILED
- PAYMENT.SALE.COMPLETED
- PAYMENT.SALE.REFUNDED
- PAYMENT.SALE.REVERSED

Localhost cannot receive PayPal deliveries. An HTTPS staging deployment or
appropriately configured development forwarding endpoint is needed.
Record the actual webhook ID, not an event ID.

Verification uses PayPal's signature-verification API. PayPal's generic webhook
simulator does not support this postback verification method; use real sandbox
subscription events for end-to-end testing.

## Application flow

/account/subscription displays checkout, payment status, and cancellation.
Checkout, refresh, and cancel are authenticated CSRF-protected POSTs under
/billing/paypal/. The return GET only displays a message. It never grants access
based on query parameters.

The webhook is the only new CSRF exception. Signatures are verified before
processing; the server fetches subscription details and matches the local
reference, provider subscription ID, and plan. An active remote subscription
without a matching payment and future paid period does not unlock tools.

Payment events are deduplicated transactionally. Older events cannot replace a
newer recorded event. Failed/refunded/reversed payments place an access hold
until a newer qualifying payment. Suspended, expired, and past-due subscriptions
are blocked. Cancellation stops renewal while retaining a confirmed paid period.

Checkout retries reuse the stored request and PayPal-Request-Id. Unresolved
creation requests older than 23 hours require support reconciliation against
PayPal before any retry record is removed; do not blindly create another
subscription or delete billing records.

Sandbox entitlements do not match live-mode checks. The existing manually
subscribed administrator stays subscribed and cannot start another subscription;
use a new ordinary account to test checkout.

## Verification and deployment limits

Automated tests mock PayPal responses. They cover paid activation, missing
payment, renewals, cancellation/expiry, failed/refunded payments, wrong prices,
ownership mismatch, invalid signatures, duplicate/out-of-order events, Alpha
eligibility, checkout retries, and CSRF. Existing tool tests remain in place.

Before live deployment, complete real sandbox checkout at both prices, inspect
actual webhook deliveries, verify tool access, cancel renewal, and confirm
expiry and renewal behavior. Test on a mobile browser as well. Remote checkout
has not yet been tested because credentials and plans are not configured.

Monitor failed webhook deliveries and replay them through PayPal. A customer can
also use Check payment status to reconcile. There is no background reconciliation
scheduler or dispute-management interface in this initial version. Refund holds
are conservative and may need support review.

Use a separate database and live credentials/resources for live deployment;
sandbox Alpha redemptions must not consume real customer offers. Update public
checkout-coming-soon messaging only when live checkout is ready. This change
does not enable live billing or create real charges.

## Code locations

- app/Config/PayPal.php: environment and readiness
- app/Services/PayPalClient.php: OAuth, fixed API hosts, signature verification
- app/Services/PayPalBilling.php: checkout and entitlement synchronization
- app/Controllers/PayPal.php: routes and safe responses
- app/Services/SubscriptionAccess.php: environment-aware access checks
- app/Views/account/subscription.php: account billing UI
- app/Database/Migrations/2026-09-18-100000_AddPayPalBilling.php: schema
- tests/Feature/PayPalBillingTest.php: mocked integration coverage

## Provider documentation

[Subscription integration](https://developer.paypal.com/subscriptions/integrate),
[subscription webhook events](https://developer.paypal.com/subscriptions/webhooks),
and [webhook verification and simulator limitations](https://developer.paypal.com/api/rest/webhooks/rest/).


## 14-day introductory trial — September 20, 2026

Signup now has two steps: account creation, then PayPal payment setup with
explicit recurring-billing consent. Registration alone never grants access.
Eligible members authorize payment before receiving 14 free days. After that,
the selected monthly plan renews automatically unless canceled. Cancellation is
available during the trial and paid periods; access lasts until the confirmed
period end. Failed first payments do not extend the trial.

Run `php spark migrate --all` for `AddSubscriptionTrials` before serving this
version. It adds trial/consent fields and the pseudonymous `trial_claims` table.
The local MySQL migration has been applied.

Four separate active plans are required: the two existing no-trial monthly plans,
plus two new trial plans. Each trial plan has exactly these billing cycles:

- Sequence 1: TRIAL, DAY, interval_count 14, total_cycles 1, fixed price 0 USD.
- Sequence 2: REGULAR, MONTH, interval_count 1, total_cycles 0, fixed price
  9.99 USD standard or 4.99 USD Alpha.

The implementation follows [PayPal's trial-period documentation](https://developer.paypal.com/subscriptions/trial-period/).
It validates all terms before creating a customer agreement. Trial access requires
an ACTIVE agreement fetched from PayPal, matching local plan/reference, payer ID,
trial cycle execution and a 14-day provider schedule. Redirect parameters never
establish entitlement. Existing non-trial subscriptions still require payment.

New private environment settings:

```ini
PayPal.standardTrialPlanId = P-YOUR_STANDARD_TRIAL_PLAN
PayPal.alphaTrialPlanId = P-YOUR_ALPHA_TRIAL_PLAN
PayPal.trialHashKey = YOUR_STABLE_RANDOM_SECRET_AT_LEAST_32_CHARACTERS
```

For sandbox only, `php spark paypal:setup-trials` creates these plans using the
existing plans' products, verifies them, and saves their IDs plus a generated
hash key to `.env`. It never creates customer subscriptions or payments and
refuses live mode. This command has been run locally; all four sandbox plans
were verified remotely. Use `php spark paypal:check --remote` to recheck them.
Keep the hash key stable and private. Losing or changing it breaks historical
repeat-trial matching.

The current app.baseURL is localhost. Real sandbox webhook delivery and buyer
acceptance still require a public HTTPS staging URL registered with PayPal.
No live rollout or real payment was performed.

### Repeat-trial safeguards

Trials reserve unique keyed hashes for account, normalized email, and a
first-party device cookie. Reservation is transactional and survives account
deletion. Pending checkout retries reuse the same agreement. Five reservations
per network in 24 hours limit further trial setup; a single shared IP does not
identify a person. Browser cookies can be cleared and networks can change:
these are abuse-reduction signals, not foolproof person identification.

The payer ID is checked after PayPal confirms approval. A reused payer causes
cancellation of that new trial agreement before granting access; it never
silently converts into an immediate paid subscription. Unique claim constraints
protect simultaneous matching requests. The customer can explicitly choose a
paid subscription afterward. Failed/abandoned reservations retain their claim;
support should review such cases rather than deleting claims automatically.

IP addresses and device/email trial identifiers are stored as HMACs, not raw
values. The first-party cookie expires after one year. Trial claims are retained
to enforce eligibility, as disclosed on /privacy. Configure trusted proxies
accurately; never trust arbitrary forwarded IP headers.

### Acceptance checks

Automated tests cover plan validation, consent, account creation with no grant,
trial checkout, duplicate webhook, repeat browser/email/account/payer, shared IP,
network velocity, trial access to all three tools, cancellation, expiry, failed
first payment, paid conversion, renewal, provider isolation, invalid signatures,
and the existing QR/tool routes. Password reset remains unavailable in the
existing application; this change does not add a mail/recovery system.

On public HTTPS sandbox staging, complete a real buyer flow and verify:

1. Signup leads to payment setup, with correct standard/Alpha terms.
2. PayPal displays 14 days free and the correct recurring monthly price.
3. A verified activation webhook unlocks tools; redirects alone do not.
4. The trial end and first payment date match PayPal, including UTC time.
5. Cancel from the account during trial, refresh, and confirm no future charge.
6. A second account/browser using the same payer is canceled without access.
7. Confirm first payment, renewal, failure, cancellation, and duplicate delivery
   using appropriate sandbox fixtures/events. Do not shorten production trial
   checks or trust fabricated webhook signatures to accelerate this test.
8. Confirm mobile signup, consent, cancellation, and future-tools messaging.

## Member billing history

The subscription page loads a private billing-history section asynchronously.
`GET /account/billing-history` uses the member filter, scopes agreement selection
to the authenticated user and current PayPal environment, and is available to
canceled subscribers too. It also renders a full page without JavaScript.

Records come from PayPal's [subscription transactions endpoint](https://developer.paypal.com/api/subscriptions/v1/subscriptions-transactions).
The default is the most recent agreement and latest 90 days. Older/newer links
reach earlier date ranges back to agreement creation; the subscription selector
includes prior agreements. Provider pagination is followed only for the same
PayPal host and subscription path; duplicates are removed and an incomplete
fetch shows an error instead of a partial record presented as complete.

Each row shows payment date (UTC), original gross amount/currency, provider
status, and expandable transaction reference. Free trials or months without
transactions are not invented as paid billing cycles. Refunded payments retain
the original gross amount and carry the refund status; this is not a net-refund
ledger or tax invoice. Only transactions PayPal returns can be displayed.

No database migration, entitlement changes, or billing mutations are involved.
History requests release the session lock before contacting PayPal and use
private/no-store responses. The feature does not store card data or provider
payloads. API failures leave subscription management available and show retry
controls. Automated tests use mocked PayPal histories, including canceled and
foreign accounts, environment separation, older payments, refund statuses,
pagination, malformed/failed responses, and browser failure/retry behavior.
Verify a real sandbox buyer's payment appears before deploying live.
