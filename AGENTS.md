# Printrivo — Codex Instructions

## Project

Printrivo is a SaaS product owned by Mber Digital.

This is an EXISTING application containing three working tools:

1. Printrivo QR Generator
2. Printrivo Gang Sheet Builder
3. Printrivo Image Studio
   - Background removal
   - Image cleanup
   - Image upscaling / print preparation

These tools already work.

DO NOT rebuild, replace, redesign, or remove their existing functionality
unless specifically instructed.

The primary goal is to add SaaS infrastructure around the existing tools.

---

# Product Goal

Convert the existing tools into a subscription SaaS application.

Users should be able to:

1. Visit the public Printrivo website.
2. Learn about the tools.
3. View pricing.
4. Register for an account.
5. Purchase a monthly subscription.
6. Log in.
7. Access all three tools from a dashboard.
8. Manage their subscription.
9. Cancel/update billing.
10. Lose protected tool access when they no longer have an eligible subscription.

---

# Brand

Company:
Mber Digital

Product:
Printrivo

Positioning:
Professional print-production tools from Mber Digital.

Target users:

- Print shops
- DTF printers
- Apparel decorators
- Graphic designers
- Sign shops
- Small businesses
- Creators

Design direction:

- Clean
- Professional
- Modern SaaS
- Minimal
- Fast
- Responsive
- Desktop and mobile friendly

Avoid:

- Grunge design
- Overly playful design
- Excessive animations
- Clutter
- Print-shop clip art

---

# Current Tools

Preserve the existing functionality of:

## Printrivo QR Generator

Protected route target:

/tools/qr

## Printrivo Gang Sheet Builder

Supports user-selected 16-inch and 22-inch print widths.

Protected route target:

/tools/gang-sheet

## Printrivo Image Studio

Protected route target:

/tools/image-studio

Includes:

- Background removal
- Image cleanup
- Image upscaling

Before modifying these tools, inspect their existing implementation.

---

# SaaS Authentication

Implement:

- Registration
- Login
- Logout
- Forgot password
- Password reset
- Email verification where appropriate
- Secure sessions
- CSRF protection
- Server-side validation

Protected tools must NEVER rely only on hidden frontend navigation.

Authorization must be enforced server-side.

---

# Subscription

Initial payment provider:

Stripe

Billing:

Monthly recurring subscription.

Standard plan:
$9.99/month for all three tools.

Early-adopter offer:
Enter Alpha at registration for $4.99/month while continuously subscribed.
The optional code is validated server-side and saved as ALPHA on the user.
Registration does not start billing or grant tool access. Stripe checkout must
apply the saved promotion when billing is implemented. Do not create a fake paid
subscription from the registration code.

Advertise $9.99/month as the regular price. Only present $4.99/month with the
Alpha early-adopter discount. Use a dismissible banner to promote the code.

---

# Subscription Access

A user must:

1. Be authenticated.
2. Have an eligible subscription.

before accessing protected tools.

Conceptually:

visitor
→ register/login
→ subscription check
→ no subscription → pricing/checkout
→ active subscription → dashboard
→ protected tools

Every protected tool route must perform server-side authorization.

---

# Stripe

Use Stripe Checkout for subscription signup.

Use Stripe webhooks as the authoritative mechanism for synchronizing billing
status.

Handle relevant events such as:

- checkout.session.completed
- invoice.paid
- invoice.payment_failed
- customer.subscription.updated
- customer.subscription.deleted

Verify Stripe webhook signatures.

Webhook processing must be idempotent.

Never trust query parameters or the checkout success redirect as proof that a
user paid.

Never expose Stripe secret keys to frontend code.

Use environment variables for secrets.

Provide Stripe Customer Portal access for billing management.

---

# Database

Expected concepts:

users

- id
- first_name
- last_name
- email
- password_hash
- email_verified_at
- status
- created_at
- updated_at

plans

- id
- name
- slug
- price
- billing_interval
- provider_price_id
- is_available
- created_at
- updated_at

subscriptions

- id
- user_id
- provider
- provider_customer_id
- provider_subscription_id
- provider_price_id
- plan_id
- status
- current_period_start
- current_period_end
- cancel_at_period_end
- created_at
- updated_at

payment_events

- id
- provider
- event_id
- event_type
- payload
- processed_at

tool_usage

- id
- user_id
- tool
- action
- created_at

Adapt this schema to the framework and existing database conventions rather
than blindly replacing existing structures.

---

# Public Pages

Create or support:

/
Landing page

/pricing
Pricing

/register
Registration

/login
Login

/forgot-password
Password recovery

/terms
Terms

/privacy
Privacy

---

# Authenticated Pages

/dashboard

/account

/account/subscription

/account/billing

/tools/qr

/tools/gang-sheet

/tools/image-studio

---

# Dashboard

The authenticated dashboard should prominently display three tools:

Printrivo Gang Sheet Builder
[Open Builder]

Printrivo Image Studio
[Open Studio]

Printrivo QR Generator
[Create QR]

Also display subscription status.

---

# Landing Page

Primary headline:

Professional Print Tools.
One Simple Subscription.

Supporting message:

Build gang sheets, clean up customer artwork, remove backgrounds, upscale
images, and generate QR codes from one easy-to-use platform.

Built for print shops, designers, apparel decorators, and creators.

Primary CTA:

Get Started for $9.99/mo

---

# Early-Adopter Offer

Display the regular $9.99/month price and explain that Alpha is an optional
registration code for the $4.99/month early-adopter offer. The reduced price
continues while the subscription remains continuously active.

No invented deadline or limited-quantity claim. Checkout is not connected yet.

---

# Development Rules

Before implementing a major feature:

1. Inspect the existing code.
2. Identify the framework and conventions.
3. Identify affected files.
4. Identify database changes.
5. Explain the implementation approach.
6. Preserve existing functionality.

Do not introduce a second framework unnecessarily.

Reuse existing:

- routing
- models
- database layer
- templating
- CSS system
- JavaScript architecture
- authentication components

when appropriate.

Prefer incremental changes over rewrites.

---

# Security

Treat this as a production SaaS application.

Protect against:

- SQL injection
- XSS
- CSRF
- insecure direct object references
- unauthorized tool access
- insecure file uploads
- webhook spoofing
- session fixation
- privilege escalation

Passwords must be securely hashed.

Never log:

- passwords
- Stripe secret keys
- complete payment information
- authentication tokens

Uploaded images must be validated.

Do not trust file extensions alone.

---

# Coding Behavior

Do not make unrelated changes.

Do not refactor working modules simply because a different implementation
would be cleaner.

When a change could break an existing tool, explain the risk first.

Use migrations for database schema changes.

Keep configuration in environment variables where appropriate.

Follow existing naming conventions.

Comment complicated billing and authorization logic.

---

# Testing

Test at minimum:

- New registration
- Login
- Logout
- Password reset
- Unauthenticated protected route
- Logged-in user without subscription
- Active subscriber
- Canceled subscription
- Past-due subscription
- Failed payment
- Successful renewal
- Stripe webhook signature failure
- Duplicate Stripe webhook
- Direct URL access to tools
- Existing Printrivo QR Generator
- Existing Printrivo Gang Sheet Builder
- Existing image tools

Existing tool functionality must continue working after SaaS integration.

---

# Critical Rule

THE THREE EXISTING TOOLS ARE WORKING APPLICATION FEATURES.

The current task is primarily to wrap them with:

AUTHENTICATION
+
SUBSCRIPTION BILLING
+
AUTHORIZATION
+
DASHBOARD
+
LANDING PAGE

Do not rebuild the tools unless explicitly requested.

Public marketing and registration display $9.99/month. The Alpha promotion may
be mentioned without publishing its discounted amount; show $4.99/month only
in the account confirmation when an Alpha code has been saved.

 
## PayPal sandbox implementation — September 18, 2026

The current authorized billing provider is PayPal, sandbox first. Stripe remains
deferred. Server-side checkout, signature-verified webhooks, payment-status
checks, cancellation, and environment-specific entitlements are implemented.
Checkout remains disabled until sandbox credentials, webhook ID, and both
monthly plan IDs are configured. See docs/PAYPAL_SETUP.md (PAYPAL_SETUP.md from
this directory) for configuration and acceptance testing.

Standard pricing remains $9.99 USD/month. A saved Alpha registration code selects
the $4.99 monthly plan on the server. Registration and approval redirects never
grant access. Confirmed payment is required. After a paid Alpha subscription is
canceled, a replacement subscription uses the standard plan. Sandbox testing
must use a separate database from a future live deployment.


## 14-day trial signup — September 20, 2026

The user authorized payment setup as part of signup, a 14-day free trial, and
monthly auto-renewal afterward with cancellation anytime. Account creation now
continues to payment setup. Eligible trials grant access only after server-side
PayPal confirmation of the agreement and trial schedule, without requiring an
initial payment. Existing paid subscriptions still require confirmed payment.
Cancellation retains access through the confirmed trial or paid month.

Dedicated standard/Alpha trial plans supplement existing no-trial plans. Trial
claims use keyed account/email/device/payer hashes and IP velocity signals.
Never silently replace a declined free trial with an immediate paid charge.
Future Printrivo tools are included while subscribed with no upgrade costs.
Sandbox trial plans and the local migration are configured; public HTTPS buyer
and webhook acceptance testing remains required. See docs/PAYPAL_SETUP.md.
