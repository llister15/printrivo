(() => {
    const dialog = document.getElementById('printrivo-bug-dialog');
    const form = document.getElementById('printrivo-bug-form');
    if (!dialog || !form) return;
    const status = form.querySelector('[data-bug-status]');
    const send = form.querySelector('[data-send-report]');
    let sending = false;
    let opener;
    document.querySelectorAll('[data-report-bug]').forEach(button => button.addEventListener('click', () => {
        opener = button;
        if (!dialog.open) dialog.showModal();
    }));
    dialog.querySelectorAll('[data-close-report]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => opener?.focus());
    // Keep editor shortcuts from responding while the user types feedback.
    dialog.addEventListener('keydown', event => event.stopPropagation());
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending) return;
        sending = true; send.disabled = true; status.textContent = 'Sending your report…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', signal:controller.signal});
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.ok) throw new Error(result?.error || 'Your report could not be sent. Please check your connection or sign in again, then retry.');
            form.reset();
            status.textContent = result.message;
        } catch (error) { status.textContent = error.name === 'AbortError' ? 'Sending took too long to confirm. Your text is still here; please wait a moment before retrying.' : (error.message || 'Your report could not be sent. Please try again.'); }
        finally {
            clearTimeout(timeout);
            form.querySelector('[name="g-recaptcha-response"]')?.remove();
            sending = false; send.disabled = false;
        }
    });
})();
