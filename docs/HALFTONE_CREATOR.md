# Image Studio halftone creator

The halftone panel processes only the current image layer. Editable text remains above it. Other Image Studio tools, subscription authorization, and storage are unchanged. No database migration is required.

## Files and boundaries

- `app/Views/tools/image_studio.php`: panel controls and script loading.
- `public/assets/css/image-studio.css`: scoped compact halftone controls.
- `public/assets/js/halftone-engine.js`: defaults, tunable presets, validation, rotated area sampling, tone mapping, shape renderers, alpha clipping, and background compositing. Shared by worker and browser fallback.
- `public/assets/js/halftone-worker.js`: isolated full/preview rendering with transferred image bitmaps.
- `public/assets/js/halftone-ui.js`: settings, unit conversion, preview jobs, cancellation, and full-resolution commit preparation.
- `public/assets/js/image-studio.js`: small integration points for display, document changes, existing history, and Print Check.
- `tests/js/halftone.browser.cjs`: browser acceptance tests, including worker rendering.
- `tests/js/studio.browser.cjs`: existing editor regression test loads the new scripts.

## Shared production preview integration

The print-production expansion moves preview job ownership into `studio-preview.js` and uses `studio-worker.js` for both Halftone and the other processors. `halftone-ui.js` retains its controls, presets, and validation. Previews now cap the longest edge at 1,000 px. Export first opens the production preflight dialog. See `PRINT_PRODUCTION.md` for the current cross-tool lifecycle, file map, and tests. The dedicated `halftone-worker.js` remains available for the standalone engine stress fixture.

## Rendering

Grid centers and sampled areas rotate together around the document origin. Sampling quality selects 2 × 2, 4 × 4, or up to 16 × 16 stratified source samples per cell. High quality increases sampling density, rather than resizing the final source. Luminance uses BT.709 coefficients (0.2126, 0.7152, 0.0722).

Brightness and contrast adjust luminance. Dark artwork maps to `1 - luminance`; light artwork and Invert map to `luminance` (Invert explicitly reverses the default dark-artwork screen). Gamma, optional binary threshold, and dot gain adjust coverage. Area determines dot dimensions using square-root scaling; line thickness scales by coverage. Minimum/maximum dot sizes constrain the result, and tiny-dot filtering uses the smallest printable shape dimension. Shapes share the sampler and tone path. Circle/ellipse edges are antialiased by Canvas; optional softness adds a small blur before alpha clipping.

Defaults and the five starting presets are configuration objects in the engine. Manual changes select Custom. Threshold defaults to disabled for continuous tones. The maximum circle diameter defaults to one grid spacing; increase maximum size or gain for a denser screen.

## Preview versus Apply

Preview changes are debounced by 150 ms. Rendering feedback appears only after another 250 ms. Preview source resolution is capped at 1200 pixels on its longest edge. If the requested screen becomes smaller than two preview pixels, preview uses a coarser resolvable screen. The panel labels reduced previews as approximate. The document dimensions and pixel data are never changed by preview, Reset, or Original/Halftone comparison.

Apply always samples the full current image layer at its actual pixel dimensions with the exact requested spacing. It does not read pixels from the visible preview canvas. Export uses the existing full-resolution document composite and PNG DPI writer. Apply before exporting; export cancels any uncommitted preview.

A dedicated worker handles sampling and drawing where Worker, OffscreenCanvas, and ImageBitmap are available. Unsupported browsers use the same engine with cooperative yields. Superseded workers are terminated; generation checks also reject stale bitmap creation and fallback renders. Other edits, undo/redo, opening a new image, clearing, and exporting invalidate the preview.

## Transparency and history

Preserve source transparency weights luminance by source alpha and ignores fully transparent RGB. Clip dots to source alpha masks the result with the full-resolution source alpha; partial alpha is applied once, not squared. When clipping is disabled but preservation remains enabled, sampled cell alpha controls opacity. White/custom backgrounds are explicitly composited behind the masked dots. Transparent remains the default.

Apply returns a new canvas to Image Studio's existing `edit()` transaction, creating exactly one history entry. Preview jobs never call that transaction. Source canvases are not mutated. Undo and Redo restore the original/generated canvas and editable text using the existing snapshots.

Physical dimensions use the existing document **export DPI**, explicitly labeled as such; source DPI may still be unknown. Inches are converted to pixels using that setting. No source metadata is invented.

## Verification

Requires Node, Puppeteer, and Chrome. Run:

```sh
node tests/js/studio.browser.cjs
node tests/js/halftone.browser.cjs
HALFTONE_STRESS=1 node tests/js/halftone.browser.cjs
PORTRAIT_FIXTURE=/path/to/portrait.jpg node tests/js/halftone.browser.cjs
```

Use `NODE_PATH` if Puppeteer is installed outside this project and `CHROME_PATH` if Chrome is not at `/usr/bin/google-chrome`. The acceptance test serves only a local fixture page and static scripts; it does not bypass or alter production routes.

Coverage includes live preview and comparison, no preview history, presets/custom, inches, invalid input, stale jobs, shapes and tone controls, a grayscale gradient, white artwork, partial alpha, transparent boundaries, backgrounds, editable text, 4500 × 5400 Apply/export, exact Undo/Redo, mobile width, and worker activity. Optional stress testing renders 5400 × 7200 at 3 px spacing while monitoring main-thread timers. Optional portrait input writes a visual check to `/tmp/printrivo-halftone-portrait.png`.

## Limits

Single-color raster halftones only; no CMYK/separations. Preview can show less detail than final output, especially at fine spacing or enlarged zoom. Quality levels use stratified sampling rather than an exhaustive integral of every rotated cell. Very fine source features may need smaller spacing or High quality. Very large final renders still require substantial memory and time; existing document limits (64 MP and 12,000 px per side) remain in force. The worker keeps processing off the UI thread, but image decoding, bitmap transfers, final display, and PNG export can cause brief pauses. Non-worker fallback is slower. Confirm minimum dot size, dot gain, and softness with the printer; presets are starting points, not printer-specific calibrations.
