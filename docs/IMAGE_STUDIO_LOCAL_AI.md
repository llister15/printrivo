# Image Studio: artwork protection and local AI upscaling

## Behavior

- Result previews and applied edits that would remove all visible pixels are rejected. The previous image remains available. Alpha-mask diagnostic previews still work.
- Fully transparent imports, exports and printer submissions are rejected. Partly transparent artwork, gradients and cutouts remain supported.
- Existing Upscale 2×, Upscale 4× and Upscale for 300 DPI buttons use locally hosted ESRGAN Slim models through TensorFlow.js. Controls and layout are unchanged.
- No account, API key, paid service or image upload is involved in AI processing. The browser downloads static runtime/model files from Printrivo and performs inference locally.
- AI changes raster RGB. Original transparency is resized separately and restored. Proportional enlargement preserves editable text; nonproportional sizing retains the existing text-flattening behavior.
- Inference runs in overlapping tiles with progress shown in the existing processing dialog. The previous document is restored if loading, allocation or inference fails. There is no silent substitution of ordinary smoothing for AI.

## Limits and quality

AI supports at most 4× enlargement per operation, sources up to 16 MP and output up to 64 MP. Ordinary Apply resize remains available up to 30,000 pixels per side and 256 MP. Device memory may impose lower practical limits. On devices without GPU acceleration, AI can be slow.

ESRGAN Slim is a compact local model, not Upscale.media's engine. Equal quality is not promised. AI can alter small lettering, facial features and textures; inspect the output at 100% before printing. Undo restores the source.

## Deployment

Upload the matching `app/` and `public/` files from the update archive into the existing installation. Include the **entire** `public/assets/vendor/studio-ai/` directory: both `model.json` files, both binary shards, the JavaScript runtime and licenses. No database migration or environment change is required. Keep a backup of files replaced.

For installations whose document root is already `public/`, place the archive's `public/assets/` under that document root's `assets/`. Keep `app/` in the application's existing private location.

Do not upload the tests or npm development dependencies. No npm install/build is required on the live server. The view and worker URLs include updated asset versions to avoid stale JavaScript.

After upload, verify:

1. Load a PNG cutout, use Transparency cleanup, and choose settings that would erase it. A warning should appear while the previous image remains visible. Repeat using Apply.
2. Use moderate cleanup, apply, Undo, Redo, then export. Confirm alpha and artwork survive.
3. Use 2× and 4× on a small image. Inspect dimensions, fine detail and transparency; test Undo.
4. Test Upscale for 300 DPI, a model-file loading failure, and an over-limit AI image. Failures must keep the source unchanged.
5. Test in current desktop Chrome/Safari and a phone browser. Visual interaction and GPU inference were not verified in the development session because no browser was connected.

## Verification performed

Real canvas pixel tests exercised the recorded moderate cleanup settings, total-removal rejection, mask diagnostics and partial opacity. Both bundled models were executed on real RGB pixels with the CPU backend, with assertions for dimensions, differing neural output, preserved alpha, unchanged source, tile boundaries and tensor disposal. Export limit tests also verify that an empty document cannot be exported. PHP and JavaScript syntax checks passed.

To run the pixel tests, install `@napi-rs/canvas@0.1.80` into a temporary development directory, then run:

```sh
NODE_PATH=/path/to/test-dependencies/node_modules node tests/js/studio-artwork.test.cjs
node tests/js/studio-limits.test.cjs
```

## Bundled dependencies

- TensorFlow.js 4.11.0, Apache-2.0: https://github.com/tensorflow/tfjs/tree/tfjs-v4.11.0
- `@upscalerjs/esrgan-slim` 1.0.0, MIT, x2 and x4 RDN model weights: https://upscalerjs.com/models/available/upscaling/esrgan-slim/

The model expects RGB values in [0,255]. Normalizing to [0,1] would produce incorrect results. Twenty source pixels of context surround each 64-pixel tile; only the tile's core is placed in the output. Transparent RGB is extended from nearby visible colors before inference to reduce dark fringes. Alpha is never predicted by the network.
