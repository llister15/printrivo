'use strict';
/* Local neural upscaling. Only static model files are fetched; artwork stays on-device. */
(() => {
    const assetRoot = new URL('../vendor/studio-ai/', document.currentScript.src);
    let runtime;
    function loadRuntime() {
        if (!runtime) runtime = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = new URL('tf-4.11.0.min.js', assetRoot).href;
            script.onload = () => window.tf ? resolve(window.tf) : reject(new Error('AI runtime unavailable.'));
            script.onerror = () => { script.remove(); reject(new Error('Local AI files could not load. Reload and try again.')); };
            document.head.append(script);
        }).catch(error => { runtime = null; throw error; });
        return runtime;
    }
    // Extend edge RGB into transparent pixels before inference, avoiding dark fringes.
    // Alpha itself is never inferred by the model and is restored from the source.
    function extendColors(data, width, height) {
        const rgb = new Float32Array(width * height * 3), queue = new Int32Array(width * height);
        const seen = new Uint8Array(width * height); let tail = 0;
        for (let i = 0; i < seen.length; i++) if (data[i * 4 + 3]) {
            seen[i] = 1; queue[tail++] = i;
            for (let k = 0; k < 3; k++) rgb[i * 3 + k] = data[i * 4 + k];
        }
        for (let head = 0; head < tail; head++) {
            const i = queue[head], x = i % width, y = Math.floor(i / width);
            for (const next of [x ? i - 1 : -1, x + 1 < width ? i + 1 : -1, y ? i - width : -1, y + 1 < height ? i + width : -1]) {
                if (next < 0 || seen[next]) continue;
                seen[next] = 1; queue[tail++] = next;
                for (let k = 0; k < 3; k++) rgb[next * 3 + k] = rgb[i * 3 + k];
            }
        }
        return {rgb, visible: tail > 0};
    }
    async function resize(source, width, height, progress = () => {}) {
        const ratio = Math.max(width / source.width, height / source.height);
        if (!(ratio > 1)) return (await StudioProcessors.render(source, 'resize', {width, height})).canvas;
        if (ratio > 4.001) throw new Error('Local AI supports up to 4× per edit. Choose a smaller print size, or use Apply resize for larger dimensions.');
        // Tile inference bounds model memory. The output canvas still needs room on the device.
        if (width * height > 64000000 || source.width * source.height > 16000000)
            throw new Error('Local AI supports sources up to 16 MP and output up to 64 MP. Use Apply resize for larger print files.');
        progress('Loading local AI enhancement…');
        const tf = await loadRuntime();
        await tf.ready();
        const scale = ratio <= 2 ? 2 : 4;
        let model, output;
        try {
            model = await tf.loadLayersModel(new URL(`models/x${scale}/model.json`, assetRoot).href);
            output = StudioProcessors.canvas(width, height);
            const ctx = output.getContext('2d');
            if (!ctx) throw new Error('Not enough memory for this enlargement. Try a smaller size.');
            const input = source.getContext('2d', {willReadFrequently: true});
            const tileSize = 64, padding = 20;
            const columns = Math.ceil(source.width / tileSize), rows = Math.ceil(source.height / tileSize);
            let completed = 0;
            for (let y = 0; y < source.height; y += tileSize) for (let x = 0; x < source.width; x += tileSize) {
                const w = Math.min(tileSize, source.width - x), h = Math.min(tileSize, source.height - y);
                const left = Math.max(0, x - padding), top = Math.max(0, y - padding);
                const right = Math.min(source.width, x + w + padding), bottom = Math.min(source.height, y + h + padding);
                const tw = right - left, th = bottom - top;
                const {rgb, visible} = extendColors(input.getImageData(left, top, tw, th).data, tw, th);
                if (visible) {
                    let prediction;
                    try {
                        // ESRGAN Slim RDN takes and returns RGB in [0,255], not [0,1].
                        prediction = tf.tidy(() => model.predict(tf.tensor4d(rgb, [1, th, tw, 3])).clipByValue(0, 255));
                        const values = await prediction.data();
                        const tile = StudioProcessors.canvas(tw * scale, th * scale), tc = tile.getContext('2d');
                        const pixels = tc.createImageData(tile.width, tile.height);
                        for (let i = 0; i < tile.width * tile.height; i++) {
                            for (let k = 0; k < 3; k++) pixels.data[i * 4 + k] = values[i * 3 + k];
                            pixels.data[i * 4 + 3] = 255;
                        }
                        tc.putImageData(pixels, 0, 0);
                        // Integer destination boundaries prevent transparent seams at non-integer print sizes.
                        const dx = Math.round(x * width / source.width), dy = Math.round(y * height / source.height);
                        const dw = Math.round((x + w) * width / source.width) - dx, dh = Math.round((y + h) * height / source.height) - dy;
                        ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
                        ctx.drawImage(tile, (x - left) * scale, (y - top) * scale, w * scale, h * scale, dx, dy, dw, dh);
                        tile.width = tile.height = 1;
                    } finally { prediction?.dispose(); }
                }
                completed++;
                progress(`Enhancing artwork locally… ${Math.round(completed / (columns * rows) * 100)}%`, completed / (columns * rows) * 100);
                await tf.nextFrame();
            }
            // Preserve cutouts, semitransparent shading and holes, never an opaque AI background.
            ctx.globalCompositeOperation = 'destination-in';
            ctx.drawImage(source, 0, 0, width, height);
            ctx.globalCompositeOperation = 'source-over';
            await StudioProcessors.preserveArtwork(source, output);
            return output;
        } catch (error) {
            if (output) output.width = output.height = 1;
            throw new Error('AI enlargement could not finish. Your original image is unchanged. ' + error.message);
        } finally { model?.dispose(); }
    }
    window.StudioUpscale = {resize};
})();
