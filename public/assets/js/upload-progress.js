/* Shared artwork progress and metadata upload. */
window.dtfProgress = (() => {
    let busy = false;
    const box = () => document.getElementById('uploadProgress');
    const label = () => document.getElementById('uploadProgressLabel');
    const bar = () => document.getElementById('uploadProgressBar');
    function show(message, percent = null) {
        box().hidden = false;
        label().textContent = message;
        if (percent === null) bar().removeAttribute('value');
        else bar().value = percent;
    }
    return {
        start() { if (busy) return false; busy = true; show('Preparing artwork…'); return true; },
        show,
        finish(message) { busy = false; show(message, 100); },
        async upload(file) {
            const captcha = window.printrivoRecaptcha ? await window.printrivoRecaptcha.token('artwork_upload') : null;
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', document.body.dataset.uploadUrl);
                xhr.setRequestHeader(document.querySelector('meta[name="csrf-header"]').content, document.querySelector('meta[name="csrf-token"]').content);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.responseType = 'json';
                xhr.timeout = 600000;
                show(`Uploading ${file.name}…`, 0);
                xhr.upload.onprogress = event => {
                    const percent = event.lengthComputable ? Math.round(event.loaded / event.total * 100) : null;
                    show(percent === 100 ? `Upload sent. Reading ${file.name}…` : `Uploading ${file.name}${percent === null ? '…' : ` — ${percent}%`}`, percent === 100 ? null : percent);
                };
                xhr.upload.onload = () => show(`Upload sent. Reading ${file.name}…`);
                xhr.onload = () => {
                    const result = xhr.response;
                    if (xhr.status >= 200 && xhr.status < 300 && result?.ok) resolve(result.image);
                    else reject(new Error(result?.error || `Upload failed (HTTP ${xhr.status}). Try a smaller image or check the server upload limit.`));
                };
                xhr.onerror = () => reject(new Error('Upload connection failed. Check your connection and try again.'));
                xhr.ontimeout = () => reject(new Error('Upload timed out. Try a smaller image or a faster connection.'));
                xhr.onabort = () => reject(new Error('Upload cancelled.'));
                const data = new FormData();
                data.append('image', file);
                if (captcha) data.append('g-recaptcha-response', captcha);
                xhr.send(data);
            });
        },
        async pngInfo(file) {
            // Read only PNG chunk headers and resolution, never the full artwork.
            const signature = new Uint8Array(await file.slice(0, 8).arrayBuffer());
            if (![137,80,78,71,13,10,26,10].every((v,i) => signature[i] === v)) return null;
            let offset = 8;
            while (offset + 12 <= file.size) {
                const header = new DataView(await file.slice(offset, offset + 8).arrayBuffer());
                const length = header.getUint32(0);
                const type = String.fromCharCode(...new Uint8Array(header.buffer, 4, 4));
                if (offset + length + 12 > file.size) return null;
                if (type === 'pHYs' && length === 9) {
                    const data = new DataView(await file.slice(offset + 8, offset + 17).arrayBuffer());
                    const x = data.getUint32(0), y = data.getUint32(4);
                    if (data.getUint8(8) === 1 && x > 0 && y > 0) return {dpi: Math.min(x,y) * 0.0254, dpiMissing: false};
                }
                if (type === 'IDAT' || type === 'IEND') return {dpi: 300, dpiMissing: true};
                offset += length + 12;
            }
            return null;
        }
    };
})();
