# Google Analytics 4 setup

1. Create a GA4 property and Web data stream for the production HTTPS domain.
2. In the stream settings, turn OFF Enhanced measurement, including automatic
   page/history changes, form interactions, site search, downloads and outbound
   clicks. This integration sends its own explicit events and sanitized page URL.
   Do not install another Google tag or Tag Manager container alongside it.
3. Keep Google signals and advertising personalization disabled. Configure your
   property's retention, internal-traffic exclusions, and email/query redaction.
4. Set privately in the production .env:

   ```ini
   analytics.enabled = true
   analytics.measurementId = 'G-YOUR_REAL_ID'
   ```

Tracking is off by default. No Google Analytics script is requested until the
visitor accepts. Decline is equally available; Analytics preferences lets them
change the choice. Choices last 180 days in browser storage. Withdrawal disables
future collection and removes accessible GA cookies; it cannot erase prior data.

Only guest home, pricing, Terms and Privacy pages are eligible. Login,
registration, account, admin, payment and editor pages are excluded, as are all
signed-in visits. The events are page_view, trial_cta_click and tool_demo_select.
Demo events include only a fixed tool name. Page URLs exclude queries/fragments;
referrer is blank. No user IDs, email, names, artwork or form fields are sent by
this integration. trial_cta_click is intent, not a completed signup or purchase.
Campaign URL attribution is intentionally unavailable with query removal.

On staging, verify in browser Network that no googletagmanager.com or analytics
requests occur before consent or after declining. Accept, then inspect GA4
Realtime/Tag Assistant for one page view and the two interaction events. Verify
query strings are absent from page_location, preferences persist, and private
pages have no tag. Repeat on production with internal traffic excluded. Ad
blockers and consent refusal will reduce measured visits.

Google references:
- https://developers.google.com/tag-platform/security/concepts/consent-mode
- https://support.google.com/analytics/answer/6366371

This is separate from reCAPTCHA and essential session/trial cookies.
