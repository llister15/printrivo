(() => {
    const container = document.getElementById('billing-history-content');
    if (!container) return;
    let pending;
    async function load(url, focus = false) {
        if (pending) pending.abort();
        const controller = new AbortController();
        pending = controller;
        const timeout = setTimeout(() => controller.abort(), 45000);
        container.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: controller.signal, credentials: 'same-origin'});
            if (!response.ok || response.redirected) throw new Error('History unavailable');
            const html = await response.text();
            if (pending !== controller) return;
            container.innerHTML = html;
        } catch (error) {
            if (pending !== controller) return;
            container.replaceChildren();
            const message = document.createElement('p');
            message.textContent = 'Billing history could not load. Please try again, or log in again if your session has expired.';
            const retry = document.createElement('a');
            retry.href = url; retry.dataset.historyLink = ''; retry.className = 'text-link'; retry.textContent = 'Try again →';
            container.append(message, retry);
        } finally {
            clearTimeout(timeout);
            if (pending === controller) {
                container.removeAttribute('aria-busy');
                if (focus) { container.tabIndex = -1; container.focus(); }
            }
        }
    }
    container.addEventListener('click', event => {
        const link = event.target.closest('a[data-history-link]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault(); load(link.href, true);
    });
    load(container.dataset.historyUrl);
})();
