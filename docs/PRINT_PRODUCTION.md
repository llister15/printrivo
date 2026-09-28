# Printrivo Image Studio — print production expansion

## Scope and files

The existing Image Studio remains a PHP view with browser-side canvas editing. Its existing image/text document and history are retained. No framework, database, authentication, billing, or server-route changes are required.

Modified:

- `app/Views/tools/image_studio.php` — six collapsed tool groups; Print Check first on the right; Text & Layers retained in a collapsible section; measurement/mirror toolbar; export dialog; script loading.
- `public/assets/css/image-studio.css` — compact production controls, check statuses, palette controls, export dialog, and responsive layout.
- `public/assets/js/image-studio.js` — processor integration, document/preview display, geometry/text handling, print intent, DPI-aware history, measurement, mirrored export, preflight invalidation, trim padding, centering, and existing-upscaler integration.
- `public/assets/js/halftone-ui.js` — existing halftone controls now use the shared preview/rendering service.
- `tests/js/studio.browser.cjs` — existing editor regression harness updated for nested controls, new scripts, and export confirmation.
- `tests/js/halftone.browser.cjs` — existing halftone acceptance harness updated for shared processing and export preflight.
- `docs/HALFTONE_CREATOR.md` — shared-preview integration note.

Created:

- `public/assets/js/studio-preview.js` — single active preview coordination, debouncing, cancellation, worker jobs, fallback, and transferable bitmap conversion.
- `public/assets/js/studio-worker.js` — shared worker entry for image analysis and processing.
- `public/assets/js/studio-processors.js` — reusable analysis, color matching, alpha cleanup, geometry, outlines, sharpening, quantization, and adapters for established background/color algorithms.
- `public/assets/js/studio-preflight.js` — six reactive checks, explanations, severity aggregation, and non-destructive Quick Fix navigation.
- `public/assets/js/studio-tools.js` — data-driven production controls, validation, eyedropper, palettes, and shared tool behavior.
- `public/assets/js/studio-legacy-preview.js` — connects the established background-removal and color-adjustment controls to shared previews without changing their algorithms.
- `public/assets/js/studio-export.js` — export preflight, output geometry validation, and export-copy options.
- `tests/js/production.browser.cjs` — production workflow, image fixtures, alpha, geometry, history, text, export, responsive layout, worker lifecycle, and optional large-file tests.
- `docs/PRINT_PRODUCTION.md` — this report.

## Architecture

**Preview:** All new processing tools, Halftone, background removal, and color adjustments use `StudioPreview.Preview`. Controls debounce for 150 ms. Preview sources are at most 1,000 px on the long edge. Only one tool owns the display at a time. Original, Result, and applicable mask views never mutate document pixels. Closing a tool or its parent, Cancel Preview, another edit, Undo/Redo, opening/clearing an image, and exporting invalidate pending preview jobs. Generations and worker termination prevent stale results. Straighten previews composite text at preview resolution rather than creating a full-size composite on every slider movement.

**Full resolution:** Apply passes the actual document image to the same processor in a worker. It converts the returned bitmap into the new image canvas only after successful processing. Document geometry is validated against the established 12,000 px / 64 MP limits. Non-worker browsers use the same processing functions with cooperative yielding where applicable. Pixel processing is separate from DOM handlers.

**Print Check:** Full-resolution analysis reads pixels in strips and counts visible, transparent, partial-alpha, and very low-opacity pixels. It finds visible bounds and perimeter contact and estimates whether the opaque perimeter has a uniform color. The UI derives Resolution, Print size, Background, Edge quality, Color, and Canvas results. Backgrounds are warnings, not errors. An empty image, invalid print dimensions, or extremely low effective resolution can produce an error. Export remains available for warnings and print-quality errors if a valid PNG can still be produced. Only impossible output dimensions block export.

Analysis is cached by committed image identity and editable text content/positions. It includes text in the composite. Slider previews do not analyze a temporary result as though it were the committed document. Every commit, Undo/Redo, text change/drag, import, and Clear updates preflight. Print-size and DPI edits update derived checks immediately without rescanning pixels.

**History:** Existing immutable canvas snapshots remain the only history system. Apply creates one entry; previews, Cancel, Reset settings, measurements, and mirror selection create none. Snapshots also retain the DPI and requested print dimensions. Padding, centering, and axis-aligned crops translate editable text positions. Straightening explicitly flattens text into the rotated image; Undo restores the editable layers. Reset Image and Clear retain their established behavior.

## Feature status

| Requested feature | Status | Implemented behavior |
| --- | --- | --- |
| Shared before/after architecture | ✓ Complete | One preview/job implementation used across processing tools; Original/Result plus applicable masks, cancellation and stale-job rejection. |
| Reactive Print Check / assistant | ✓ Complete | Six compact checks, detailed findings, PASS/WARNING/ERROR, automatic issue count and overall status. |
| Quick Fixes | ✓ Complete | Open the relevant controls; opening a tool can show a temporary preview but never commits an edit. |
| Empty/good/error states | ✓ Complete | No-image guidance; no-issue state; warnings versus errors; no invented fixed issue count. |
| Smart print sizing / DPI | ✓ Complete | Effective DPI from selected inches, recommended maximum sizes at 300/200/150 DPI, export-size mismatch warnings, metadata changes without automatic resampling. |
| Upscale recommendation | ✓ Complete | Opens existing resize/upscale controls; explicit Upscale for 300 DPI reuses the existing smooth resampler. |
| Remove Color | ✓ Complete | Image eyedropper, color picker, perceptually weighted color distance, tolerance, softness, optional preservation of partial-alpha pixels, Original/Result/selection mask. |
| Replace Color | ✓ Complete | Eyedropper, source/target color, tolerance, relative-luminance shading preservation, exact replacement when disabled, selection mask. |
| Transparency Cleanup | ✓ Complete | Opacity cutoff, alpha threshold, edge erosion, hardness, isolated single-pixel removal, zeroing transparent RGB, artwork/transparency/alpha-mask views. Neutral defaults preserve alpha gradients. |
| Trim / padding / center | ✓ Complete | Visible-bounds trim with optional padding (2 px default retained), linked/unlinked four-sided padding in pixels/inches, centering without scaling. Text offsets follow geometry. |
| Outline / stroke | ✓ Complete | Visible-alpha silhouette; Euclidean round or square-morphology sharp boundaries; outside/center/inside, color, width, opacity. |
| Clean Edges | ✓ Complete | Alpha smoothing, morphological speckle removal and hole closing, hardness, matte/fringe decontamination, mask comparison. Raster processing only. |
| Sharpen | ✓ Complete | Alpha-aware unsharp mask using a three-pass box approximation to Gaussian blur; amount, radius, threshold; alpha retained. |
| Crop / straighten | ✓ Complete | Free/original/preset/custom ratios; position and size in px/inches; ±45° rotation, full-canvas Straighten action, preview, Apply, and Trim to Artwork action. Existing exact-pixel crop retained. |
| Reduce Colors | ✓ Complete | 2–16 color weighted palette clustering, generated editable swatches, explicit regeneration, optional error-diffusion dithering, alpha preservation. |
| Measurement | ✓ Complete | Drag width/height/distance in pixels/inches using document DPI; Escape exits; overlays excluded from export. |
| Export preflight | ✓ Complete | Actual PNG output dimensions/DPI, RGB surface, background information, issues, Go Back & Fix, Export Anyway, optional trim/padding. |
| Mirror for transfer | ✓ Complete | Explicit toolbar/dialog state and warning; preview and exported copy mirror horizontally; underlying document unchanged. |
| Sidebar organization | ✓ Complete | Exact six requested top-level groups; Print Check / Quick Fixes / Status first on the right; existing Text & Layers below. |
| Processing feedback / validation | ✓ Complete | Debounced preview feedback, commit feedback, disabled controls during Apply, numeric and geometry validation. |
| Memory / large-image handling | ✓ Complete within existing limits | Preview cap, worker termination, bitmap closure, bounded history, strip-based analysis, no slider snapshots. See limits below. |
| Detect original file color mode/profile | △ Limited | Canvas RGB/sRGB compatibility is known; source JPEG/PNG color mode or embedded ICC profile is not inferred from decoded pixels. |
| Photoshop/vector/CMYK/RIP/ICC/generative features | ✕ Not implemented, as requested | No new typography engine, drawing tools, RAW editing, or separation workflow. |

## Processing details and limits

- Print Check is evidence-based preflight guidance, not a printer/RIP certification. Semi-transparency can be intentional. The edge checks flag actual low-alpha/partial-alpha counts; they do not claim to identify every jagged edge, halo, or photographic subject.
- Color removal/replacement uses a normalized red-mean weighted RGB distance, an efficient perceptual approximation rather than a color-managed Lab comparison. Color-based background removal is not subject-aware AI.
- Preserving partially transparent edges in Remove Color leaves those pixels intact. Turn it off when those same pixels are unwanted residue.
- Cleanup/edge controls can remove fine detail when deliberately increased. Defaults for Transparency Cleanup leave gradients unchanged. Fringe Auto estimates a black/white matte from neighboring opaque pixels; choose an explicit matte if needed.
- Round outlines use a distance transform; sharp outlines use square morphology. They follow every nonzero-alpha pixel, including faint residue. Clean the alpha first if needed. Output dimensions stay fixed; add padding before an outside outline that would reach the canvas edge.
- Straightening rotates within the existing canvas, so rotated corners may be clipped. It flattens text as stated in the UI; Undo restores layers. Axis-aligned crop and padding keep text editable.
- Fine effects at fit zoom are approximations. Full-resolution Apply is authoritative. Full-resolution sharpen, outline, and morphology can use several working buffers. Large files still need substantial browser memory even when the UI thread is free.
- Smooth upscaling increases pixel dimensions but cannot restore missing detail. Print Check reports pixel density, not recovered photographic information.
- PNG output follows the document **export DPI**. Requested inches that do not match current pixels/DPI trigger a Print size warning. Apply Resize to match the requested inches; metadata changes alone do not silently resample.
- Mirror state is visible and adds `-mirrored` to the export filename. The legacy destructive Flip action explicitly warns when export mirroring is also on.
- Export does not automatically import into Gang Sheet Builder. The exported PNG is the handoff artifact for the existing builder workflow.

## Testing

Requires Node, Puppeteer, and Chrome. If Puppeteer is installed elsewhere, set `NODE_PATH`; set `CHROME_PATH` for a non-default browser path.

```sh
node tests/js/studio.browser.cjs
node tests/js/halftone.browser.cjs
node tests/js/production.browser.cjs
PRODUCTION_STRESS=1 PORTRAIT_FIXTURE=/path/to/portrait.jpg node tests/js/production.browser.cjs
HALFTONE_STRESS=1 node tests/js/halftone.browser.cjs
```

The local browser harness loads the real view controls/scripts and serves workers from the same origin. Production authorization routes are not changed.

Verified:

- Existing background removal, trim, resize/upscale, text editing, crop/rotate, color edits, undo/redo, PNG DPI, Reset, Clear, and fresh import.
- All nine newly registered processors: actual preview, no preview history, Cancel, full Apply, exact source restoration by Undo, and exact Redo.
- Editable text retained through padding; explicitly flattened by straightening; restored by Undo.
- Eyedropper sampling, generated palette edits, effective DPI, non-destructive Quick Fix navigation, geometry validation, responsive sidebar layout.
- Export dialog, mirrored filename/state, trim/padding dimensions, PNG alpha/DPI, unchanged document, measurement overlay exclusion, and Escape.
- JPG white/black backgrounds, white/black transparent logos, partially transparent artwork, low-resolution source, alpha gradient, edge-touching artwork, excessive padding, two-color art, empty art, text, and a local portrait photo.
- Halftone regression: controls, shapes, stale jobs, alpha, full 4500 × 5400 Apply and PNG export.
- Worker lifecycle: completed/canceled work terminates its worker; tests check no lingering workers after settled analysis.

Recorded on this development machine (not a performance guarantee): 4500 × 5400 color removal plus full-resolution preflight took about 1.3 seconds. Main-thread 10 ms timers continued firing, with a maximum observed gap of 55 ms during the tested operation. The previously established 5400 × 7200 halftone at 3 px spacing took about 14 seconds in a worker. Canvas allocations and PNG encoding can still cause brief pauses; low-memory devices have less headroom. No claim is made that JavaScript heap metrics alone measure native canvas memory.
