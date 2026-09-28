/* Obtain a fresh v3 token at submission time; never reuse a previously sent token. */
(() => {
    const key = document.querySelector('meta[name="recaptcha-site-key"]').content;
    const waiting = new WeakSet();
    const verified = new WeakSet();
    async function token(action) {
        return new Promise((resolve, reject) => {
            let interval;
            const timeout = setTimeout(() => { clearInterval(interval); reject(new Error('Security verification could not load. Check your connection or browser blocker, then try again.')); }, 12000);
            interval = setInterval(() => {
                if (!window.grecaptcha?.ready || !key) return;
                clearInterval(interval);
                window.grecaptcha.ready(() => {
                    Promise.resolve().then(() => window.grecaptcha.execute(key, {action})).then(value => {
                        clearTimeout(timeout);
                        if (typeof value !== 'string' || !value) throw new Error('Missing verification');
                        resolve(value);
                    }).catch(() => { clearTimeout(timeout); reject(new Error('Security verification failed. Please try again.')); });
                });
            }, 50);
        });
    }
    window.printrivoRecaptcha = {token};
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;
        if (new URL(form.action, location.href).origin !== location.origin) return;
        if (verified.has(form)) { verified.delete(form); return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (waiting.has(form)) return;
        waiting.add(form);
        let message = form.querySelector('[data-recaptcha-message]');
        if (!message) {
            message = document.createElement('p');
            message.dataset.recaptchaMessage = '';
            message.setAttribute('role', 'status');
            form.append(message);
        }
        message.textContent = 'Verifying…';
        try {
            const value = await token('form_submit');
            let input = form.querySelector('[name="g-recaptcha-response"]');
            if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'g-recaptcha-response'; form.append(input); }
            input.value = value;
            message.textContent = '';
            verified.add(form);
            form.requestSubmit(event.submitter || undefined);
        } catch (error) { message.textContent = error.message; }
        finally { waiting.delete(form); verified.delete(form); }
    }, true);
})();
