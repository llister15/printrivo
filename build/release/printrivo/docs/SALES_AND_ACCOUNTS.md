# Public sales page and member accounts

Implemented at the user's request after the CodeIgniter migration. The user chose
**account signup now; Stripe checkout next**.

## Customer experience

- `/` and the root index alias serve a public sales page with Mber Digital branding,
  a tool illustration, practical benefits for all three tools, workflow overview,
  the $4.99/month Founding Supporter offer, FAQs, and account-registration CTAs.
- `/pricing` presents the offer and continuous-subscription price-retention terms.
  There is no invented deadline, testimonial, trial, or payment confirmation.
- `/register` creates an account with first/last name, email and a securely hashed
  password. The member is logged in and sent to `/dashboard`.
- `/login` and CSRF-protected POST `/logout` handle returning members.
- `/dashboard` preserves the previous tool dashboard behind server-side login,
  shows account/subscription status and links to `/account/subscription`.
- An account without an eligible subscription sees locked tool cards and the
  checkout-coming-soon explanation. Account creation does not charge the member,
  reserve the founding price, or manufacture a subscription.
- All new and legacy tool routes, including JSON endpoints and private logo URLs,
  require login and an active subscription with an unexpired paid-through date.
  Pending cancellation retains access until that date. Past-due, canceled, unpaid,
  incomplete and paused subscriptions do not grant access.

The browser image-processing and print/export algorithms remain intact. Navigation
returns to the authenticated dashboard. The public page has a separate light theme
using Mber Digital's navy/steel-blue palette; the editors retain their dark theme.

## Authentication and storage

Uses CodeIgniter Shield 1.4.1, its user identities, password validation/hashing,
login session regeneration and login-attempt recording. Registration requires
12-character minimum passwords and server-side confirmation/name/email validation.
Auth POSTs use Shield's rate limiter. Sessions and CSRF use CodeIgniter's session
backend; persistent remember-me cookies are disabled. Disabled/banned users are
blocked independently of Shield's optional email-activation policy. Customer pages
are not collected by the debug toolbar.

The default connection is a private SQLite database at `writable/mber.sqlite`.
Migrations install Shield/Settings tables and add user first/last names plus a
subscription-status table. No paid subscription rows are seeded. This is the local
working default, not a cloud database. CI's connection can be configured for another
supported database before deployment; migrate and back up deliberately.

Main files:

- `app/Controllers/Marketing.php`, `Login.php`, `Register.php`, `Account.php`
- `app/Views/marketing/`, `app/Views/auth/`, `app/Views/account/`
- `public/assets/css/marketing.css`
- `app/Views/dashboard.php`, `app/Controllers/Dashboard.php`
- `app/Filters/MemberAccess.php`, `app/Services/SubscriptionAccess.php`
- `app/Models/UserModel.php`, `app/Config/Auth*.php`
- `app/Database/Migrations/2026-09-18-060000_AddMemberProfiles.php`
- Route, filter, session-CSRF, autoload and database configuration

## Deliberately pending

Stripe signup/Checkout, webhook synchronization, Customer Portal and plan catalogue
remain next-phase work. The existing subscription table is only the authorization
projection; paid access must eventually be written from verified provider events,
not from a signup request or success URL. Future billing migrations should add plan
and payment-event structures without replacing Shield's identities.

Outbound mail, account verification and password recovery are not yet configured.
No recovery UI claims to send email. Customer legal pages and production billing
terms still need to be completed before accepting subscriptions. Configure HTTPS
and secure cookies for production. The existing PIN-based rate override remains a
compatibility mechanism and requires the planned role-based replacement.

## Verification

- PHPUnit covers public pages, anonymous/private redirects, canonical and legacy
  tool access, unpaid-member denial, active/expired/canceled/past-due eligibility,
  disabled accounts, private paths, CSRF and QR PNG rendering.
- The existing Node gang-sheet regression suite remains green.
- `tests/integration/account_smoke.py` exercises real signup, password hashing,
  login/logout, disabled-account enforcement and subscription gating with one
  disposable local account, removed afterward.
- `tests/integration/http_smoke.py` now accepts subscriber test credentials via
  environment variables and verifies the actual protected tool workflows.
- Chrome screenshots checked the public sales page on desktop/mobile and the
  registration form. No live payments, production deployment or customer messages
  were sent.
