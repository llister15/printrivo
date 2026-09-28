'use strict';
window.PrintOrderSender = (() => {
    const box = document.getElementById('print-order-feedback');
    let sending = false;
    box.querySelector('button').onclick = () => { box.hidden = true; };
    function feedback(message, error = false) {
        box.querySelector('span').textContent = message;
        box.dataset.error = String(error); box.hidden = false;
    }
    function ready() {
        if (box.dataset.connected === '1') return true;
        feedback('Send to printer is not connected yet. Please contact the administrator.', true);
        return false;
    }
    function validate(width, height) {
        if (width * height > 100000000) throw new Error('CSW accepts artwork up to 100 MP. Download this design and contact CSW to arrange a larger file.');
    }
    const requests = new Map();
    async function send({blob, filename, tool, dpi, width, height}) {
        if (sending) return;
        sending = true;
        try {
            validate(width, height);
            if (blob.size > 8 * 1024 * 1024) throw new Error('CSW accepts artwork up to 8 MB. Download your PNG and contact CSW to arrange a larger file.');
            if (!ready()) throw new Error('Send to printer is not connected yet.');
            // Preserve the request ID across retries, including a reload in the same tab.
            const bytes = await blob.arrayBuffer();
            const hash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)), n => n.toString(16).padStart(2, '0')).join('');
            const key = `printrivo-print:${tool}:${dpi}:${filename}:${hash}`;
            let id = requests.get(key);
            if (!id) { try { id = sessionStorage.getItem(key); } catch {} }
            if (!id) id = crypto.randomUUID();
            requests.set(key, id);
            try { sessionStorage.setItem(key, id); } catch {}
            PrintProgress.update('Verifying your print request…');
            if (box.dataset.captchaRequired === '1' && !window.printrivoRecaptcha) {
                throw new Error('Security verification could not load. Refresh Image Studio and allow reCAPTCHA in your browser, then retry.');
            }
            const captcha = window.printrivoRecaptcha ? await window.printrivoRecaptcha.token('form_submit') : null;
            const authorization = new FormData();
            authorization.append('request_id', id);
            if (captcha) authorization.append('g-recaptcha-response', captcha);
            const verified = await fetch(box.dataset.authorizeEndpoint, {
                method:'POST', body:authorization, credentials:'same-origin',
                headers:{[box.dataset.csrfHeader]:box.dataset.csrfToken,'X-Requested-With':'XMLHttpRequest'},
                signal:AbortSignal.timeout(30000),
            });
            const grant = await verified.json().catch(() => null);
            if (!verified.ok || !grant?.ok || !grant.upload_ticket) {
                throw new Error(grant?.error || 'Security verification could not finish. Refresh the tool and try again.');
            }
            const data = new FormData();
            for (const [name, value] of Object.entries({request_id:id, filename, tool, dpi})) data.append(name, value);
            data.append('artwork', blob, filename);
            PrintProgress.update('Uploading artwork to CSW…', 0);
            const result = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', box.dataset.endpoint);
                xhr.setRequestHeader(box.dataset.csrfHeader, box.dataset.csrfToken);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('X-Printrivo-Upload-Ticket', grant.upload_ticket);
                xhr.responseType = 'json'; xhr.timeout = 900000;
                xhr.upload.onprogress = event => {
                    const percent = event.lengthComputable ? Math.round(event.loaded / event.total * 100) : null;
                    PrintProgress.update(percent === 100 ? 'Creating your CSW order…' : `Uploading artwork${percent === null ? '…' : ` — ${percent}%`}`, percent === 100 ? null : percent);
                };
                xhr.onload = () => xhr.status >= 200 && xhr.status < 300 && xhr.response?.ok
                    ? resolve(xhr.response)
                    : reject(new Error(xhr.response?.error || 'The order was not confirmed. Check your connection or sign in again, then retry the same artwork.'));
                xhr.onerror = xhr.ontimeout = () => reject(new Error('The order was not confirmed. Click Send to printer again to retry the same request safely.'));
                xhr.send(data);
            });
            feedback(`${result.duplicate ? 'Already sent' : 'Sent to CSW'} — order ${result.order_number}. One copy requested. CSW will review pricing and production; no payment was collected.`);
            return result;
        } catch (error) { feedback(error.message || 'The print request could not be sent.', true); throw error; }
        finally { sending = false; }
    }
    return {send, ready, validate, feedback};
})();
