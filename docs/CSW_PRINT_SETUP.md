# CSW print orders

The Gang Sheet Builder and Image Studio now have a **Send to printer** button. A click prepares the finished PNG, then opens a confirmation dialog with pickup or shipping. Shipping requires a recipient and address and is quoted separately. The print subtotal is $0.03 per square inch with a $15 minimum for one copy, recalculated on the server from PNG dimensions and DPI. Confirmation creates an order using the signed-in member's name and email; no payment is collected. Tax and shipping are additional.

## Configure

1. Configure the token for the selected CSW endpoint. The destination was changed to `/webhooks/orders` at the user’s request. The locally available general receiver accepts only 256 KB of order JSON and does not accept the DTF artwork payload; its live receiver must be updated before this integration can send artwork successfully.
2. Add the token to the deployed Printrivo `.env`:

   ```dotenv
   cswprint.token = "PASTE_WEBHOOK_TOKEN_HERE"
   ```

   The local `.env` and the `env` sample have a blank placeholder. Never put the token in JavaScript, HTML, source control, or a deployment ZIP.
3. Deploy the changed/new files listed below and run `php spark migrate --all` from the Printrivo project directory. The new `print_orders` table records request IDs, payload hashes, rate-limit cooldowns, and receipts; it does not store image data or tokens.
4. Ensure PHP accepts a 500 MB file plus multipart overhead (`upload_max_filesize` at least `500M`, `post_max_size` at least `512M`). Large PNGs stream from temporary files instead of being embedded in base64 JSON. Allow up to 900 seconds for large uploads and forwarding. Keep at least `128M` PHP memory for the application and small inline imports.
5. With an eligible subscriber, send a small test PNG from each tool when you are ready to create actual CSW orders. Confirm the attached artwork, customer, dimensions, one-copy quantity and order number in CSW. Repeat the same send to verify it returns the existing order. This live acceptance test has not been performed.

Destination: `https://app.cswprint.com/index.php/webhooks/orders`.
The token is read only on the server and sent as `X-FlowPress-Token`. Redirects are disabled. The browser sends to Printrivo's authenticated, subscription-protected, CSRF-protected endpoint.

## Artwork and retries

- Gang sheets preserve arrangement, transparency and 300 DPI metadata. Image Studio sends the current full canvas, including text and the mirror setting, with the selected DPI. Export-dialog trim/padding options do not alter the document and do not apply to the Send button.
- The coordinated large-artwork update supports PNGs up to **500 MB and 256 MP**. Files above 8 MB use multipart uploads with a SHA-256 checksum; smaller files retain the existing inline JSON format. Nothing is silently downscaled. Deploy the updated CSW receiver before deploying this sender. The prior receiver rejects files over 8 MB.
- Requests include dimensions in inches and pixels in CSW's production specifications. The item is a DTF Gang Sheet or Print-ready artwork, quantity one, with the confirmed print subtotal and fulfillment details. Tax and shipping require CSW review.
- Browser retries retain the same request ID for the same filename, tool, DPI and PNG bytes in the same tab, including reloads when session storage is available. The server checks an immutable payload hash and returns saved receipts; CSW also deduplicates its source/external ID. A new tab may create a new order, so review uncertain orders in CSW before switching tabs or changing artwork.
- HTTP 429 honors CSW's Retry-After value. Network/server errors preserve retry identity. An uncertain result is never shown as success.

## Processing indicator

A full-width strip appears at the top while Image Studio loads, resizes, applies edits or exports, and while either tool prepares/sends a print. It makes the editor inert during processing and cannot be dismissed with Escape. It shows processing stages with an indeterminate animation; upload percentages are measured from actual upload bytes. Resize uses the existing worker architecture where available so the main UI can stay responsive. Reduced-motion preferences are respected.

## Deployment files

```
app/Config/CswPrint.php
app/Config/Routes.php
app/Config/Services.php
app/Controllers/PrintOrders.php
app/Database/Migrations/2026-09-24-090000_CreatePrintOrders.php
app/Services/CswPrintClient.php
app/Services/PrintOrder.php
app/Views/partials/print_order.php
app/Views/tools/gang_sheet.php
app/Views/tools/image_studio.php
public/assets/css/processing-progress.css
public/assets/js/processing-progress.js
public/assets/js/print-order.js
public/assets/js/print-confirmation.js
public/assets/css/print-confirmation.css
public/assets/js/image-studio.js
public/assets/js/studio-export.js
public/assets/js/studio-processors.js
public/assets/js/studio-preview.js
public/assets/js/studio-worker.js
```

Update the server `.env` in place; do not replace its database, billing or other settings with the local file.

## Validation

- `php vendor/bin/phpunit --no-coverage --filter PrintOrderTest`
- `node tests/js/print-order.test.cjs`
- `node tests/js/studio-limits.test.cjs`
- `node tests/js/builder.test.cjs`

The generated payloads were also accepted by the local CSW `DtfOrderImport` parser for both tools, preserving exact PNG bytes. No live webhook requests were made. Browser visual verification requires a connected browser.

## Large-artwork deployment (coordinated update required)

The receiver changes are staged under `build/csw-large-artwork/`, based on the local FlowPress source. Compare them with the latest CSW server files before deploying. Do not deploy the larger sender limit while the receiver is still on the old version.

CSW files:

- `app/Controllers/OrderImports.php`
- `app/Libraries/DtfOrderImport.php`
- `app/Libraries/JsonOrderImport.php`
- `public/.user.ini` and `public/.htaccess` (merge upload/time limits with existing hosting settings)

Printrivo files changed for large transfers:

- `app/Config/CswPrint.php`
- `app/Controllers/PrintOrders.php`
- `app/Services/PrintOrder.php`
- `app/Services/CswPrintClient.php`
- `app/Views/partials/print_order.php`
- `public/assets/js/print-order.js`
- `.user.ini` and `public/.user.ini` (merge with existing hosting settings)

No database migration, `.env` change, or new token is required for this update. PHP-FPM may cache `.user.ini` for several minutes. If hosting-level upload/body/time limits are lower, raise those too.

Validation: a real 9.7 MB PNG was accepted, stored intact, and deduplicated on retry by the staged receiver. All 14 CSW import/webhook tests passed (116 assertions), including the new large-file case. Missing uploads and checksum mismatches are rejected. No live CSW orders were created during testing.

## SFTP-only hosting: missing print_orders table

If CLI migration access is unavailable, the next administrator's authenticated print authorization request now installs only `CreatePrintOrders` using the application's migration code and records it in migration history. A file lock serializes setup attempts. It never runs a downward migration, unrelated migrations, or a CSW request during setup. Existing records are preserved. Non-administrators get a setup-specific message if the table is missing. Standard `php spark migrate --all` remains supported.

The production log confirmed the earlier unconfirmed-order message was caused by the missing prefixed `print_orders` table, before any CSW HTTP request. This patch is deployed separately from the pending large-artwork receiver upgrade and does not change `.env`.

## Confirmation deployment

`build/print-confirmation-fix/` contains the confirmation feature applied to the live 8 MB sender, without enabling the pending large-upload protocol. Pickup/shipping details are included in order notes and the calculated subtotal is set as the single unit print price. Changes to confirmed details under an existing retry ID are rejected to avoid silently creating a second order after an uncertain response. The server `.env` is unchanged.
