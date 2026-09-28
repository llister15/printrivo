# Admin dashboard

After registering your real owner account, run on the application server:

```sh
php spark migrate --all
php spark admin:grant YOUR_ACCOUNT_EMAIL
```

The grant command only works from the server CLI and only grants an existing,
active, unbanned account. No default production admin or password is created.
Log in and open `/admin` (or use the Admin navigation link). A subscription is
not required for administration. Ordinary members cannot access admin URLs.

The large Active members number counts unique accounts with current tool access
in the configured billing environment, including trials and canceled renewals
whose confirmed period has not ended. It excludes banned/inactive accounts.
Total users excludes soft-deleted accounts. Recent activity means a recorded
Shield last_active timestamp within 24 hours, not a live presence indicator.

Search all users by name/email, 25 per page. Manage opens the latest PayPal
agreement in the current environment. Confirm the customer's identity and request
before selecting the required confirmation checkbox and canceling. Cancellation
stops renewals; it does not refund, delete the user, or shorten confirmed access.

Admin cancellation records administrator ID, target user, subscription, timestamp,
and requested/confirmed/unconfirmed outcome in admin_actions. An unconfirmed
request may have reached PayPal; inspect PayPal before retrying. History shows the
latest 20 admin actions per user. Do not store the customer's email message,
password, or payment details in logs. All admin responses are private/no-store;
POST actions use the application's CSRF and reCAPTCHA protection.

The displayed subscription is the locally synchronized status, not a live PayPal
query on every page load. Webhooks keep it current; cancellation verifies the
agreement remotely and refuses a stale form targeting a replaced subscription.
Test an admin-requested cancellation on HTTPS sandbox before enabling live use.
