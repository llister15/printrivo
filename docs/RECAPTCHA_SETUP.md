# Google reCAPTCHA v3

reCAPTCHA is implemented but disabled until real keys are configured. No database
migration is required. Use v3 keys compatible with Google's `siteverify` API;
v2 checkbox keys and Enterprise-only assessment keys are not interchangeable.

Create a v3 key pair in the [Google reCAPTCHA console](https://www.google.com/recaptcha/admin/create)
for your actual hostname. Register localhost separately for local testing.
Place these settings in the private `.env` file, never in source control or chat:

```ini
recaptcha.enabled = true
recaptcha.siteKey = YOUR_PUBLIC_V3_SITE_KEY
recaptcha.secretKey = YOUR_PRIVATE_V3_SECRET_KEY
recaptcha.hostname = printrivo.example
recaptcha.minimumScore = 0.5
```

Use only the hostname (no scheme, path, or port), matching Google's response.
Use separate keys/settings for staging and production. With `enabled = true`,
incomplete configuration blocks submissions; it never silently bypasses checks.
The default `enabled = false` preserves current forms until real keys are ready.

## Coverage

All same-origin POST forms receive a fresh `form_submit` token just before
submission: register, login, logout, checkout, payment-status refresh,
cancellation, and QR generation. Artwork metadata uploads receive a fresh
`artwork_upload` token. A global server filter protects every POST route and
legacy tool alias, including direct requests with JavaScript disabled.
New standard POST forms in the marketing layout inherit protection.
Standalone future form pages must include `view('security/recaptcha')`.

PayPal webhooks are the sole route exemption: they use provider signature
verification rather than a browser challenge. Purely local image editing,
layout controls and exports make no form submission and are unaffected.
CSRF, authentication, subscription authorization, and upload validation remain.

The server checks Google success, exact hostname and action, configured score,
and a challenge timestamp no more than two minutes old. Google enforces
single-use tokens. Missing/rejected tokens return 403; incomplete enabled
configuration returns 503. Network errors deny the submission. AJAX uploads get
JSON errors. Secrets and tokens are never logged or flashed with form input.

The Google badge remains visible. /privacy discloses reCAPTCHA and links to
Google's privacy policy and terms. If you use a restrictive CSP outside the app,
allow Google's documented reCAPTCHA script/frame/connect origins.

## Verification

Run `php vendor/bin/phpunit --no-coverage` and, with Puppeteer installed,
`node tests/js/recaptcha.browser.cjs`. Tests use mocked Google responses and do
not prove that a real key pair/domain is registered correctly.

After configuring real keys, test signup, login/logout, every billing form,
QR generation and artwork uploads on the registered hostname. Confirm missing,
expired, reused, low-score and wrong-action tokens are rejected. Verify
webhook delivery remains unaffected. Check scores in Google's console and tune
the threshold to avoid blocking legitimate users, especially cancellation.
Users can also stop recurring payments through PayPal's automatic-payment
settings if verification is unavailable.

References: [v3 integration](https://developers.google.com/recaptcha/docs/v3),
[server verification](https://developers.google.com/recaptcha/docs/verify).
