# Tool bug reports and content-page navigation

Each tool has a bug icon and “Report a bug” button beside its dashboard link.
The shared modal collects a summary, reproduction details, and an optional
expected result. It sends a plain-text email to `info@mberdigital.com` using
CodeIgniter's existing email service. Reply-To is the authenticated member's
account email; the subject and recipient are controlled by the server.
Artwork, passwords, payment details, and page query strings are not attached.

## Configure web-host SMTP

The recipient is saved in the local private `.env`. Outgoing SMTP details are
still required. Obtain them from the web host and set:

```ini
bugreports.recipient = info@mberdigital.com
email.protocol = smtp
email.fromEmail = YOUR_VERIFIED_SENDER_ADDRESS
email.fromName = Printrivo
email.SMTPHost = YOUR_HOSTS_SMTP_SERVER
email.SMTPUser = YOUR_SMTP_USERNAME
email.SMTPPass = YOUR_PRIVATE_SMTP_PASSWORD
email.SMTPPort = 587
email.SMTPCrypto = tls
email.SMTPTimeout = 10
```

Use the port and encryption required by the host. CodeIgniter uses implicit TLS
on port 465; its Email config documents an empty SMTPCrypto for that port.
Never commit passwords or place them in templates. This implementation does not
provision mailboxes, configure DNS, or send a real test report automatically.
Confirm delivery with one intentional report after configuring SMTP, including
its Reply-To behavior and spam-folder placement.

Missing configuration or a failed send returns an error and preserves the
member's form text. Success appears only when the mail service accepts the
message; acceptance does not itself guarantee inbox delivery. There is no
persistent report queue or database migration.

## Protection and behavior

`POST /support/bug-report` requires a logged-in, active member account (a paid
entitlement is not required to report a problem), CSRF, and reCAPTCHA when
enabled. The selected tool must exist in the shared Suite registry. Fields have
server-side length limits. Requests are limited to five per member per hour.
The sender and recipient cannot be overridden by form fields. No report text,
mail passwords, or mail-debug payloads are logged by this feature.

The modal supports Escape/close and returns focus to its launcher. It does not
alter artwork state or navigate away from the tool. Only the tools' navigation
button, shared feedback assets, and modal includes were added; editor logic is
unchanged.

The back-to-top arrow is included only in the marketing/content layout and the
custom 404 page. It appears after 400 pixels of scrolling, respects reduced
motion, and leaves room for the reCAPTCHA badge. No tool includes the arrow.

## New signup notifications

Every successful public registration also attempts a plain-text notification to
info@mberdigital.com using the same SMTP sender/settings. It includes the member's
name, email, account ID, signup time, Alpha-code status, and an admin-only member
link. It does not contain passwords or payment data and does not imply payment.
Invalid registrations and logins do not trigger notifications.

SMTP failures do not block signup. The application logs a generic failure with
only the account ID; email notifications are not queued or retried automatically.
Check SMTP delivery with an intentional signup on staging before launch.
