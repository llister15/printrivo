'use strict';
// A modal progress strip makes the tool inert while preserving the artwork view.
window.PrintProgress = (() => {
    let dialog, label;
    const paint = () => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0)));
    function setup() {
        if (dialog) return;
        dialog = document.createElement('dialog');
        dialog.className = 'print-processing';
        dialog.setAttribute('aria-label', 'Processing artwork');
        dialog.innerHTML = '<div class="print-processing-copy"><strong role="status" aria-live="polite"></strong><span>Please wait — your artwork is being processed.</span></div><div class="print-processing-track" role="progressbar" aria-label="Processing artwork"><span></span></div>';
        label = dialog.querySelector('strong');
        dialog.addEventListener('cancel', event => event.preventDefault());
        dialog.addEventListener('keydown', event => event.stopPropagation());
        document.body.append(dialog);
    }
    return {
        async start(message = 'Preparing artwork…') {
            setup(); label.textContent = message;
            const track=dialog.querySelector('.print-processing-track');track.removeAttribute('aria-valuenow');track.dataset.determinate='false';track.firstElementChild.style.width='';
            if (!dialog.open) dialog.showModal();
            document.querySelector('main')?.setAttribute('aria-busy', 'true');
            await paint();
        },
        update(message, percent = null) {
            if (!label) return;
            label.textContent = message;
            const track=dialog.querySelector('.print-processing-track');
            track.dataset.determinate=String(percent !== null);
            if (percent === null) { track.removeAttribute('aria-valuenow'); track.firstElementChild.style.width=''; }
            else { track.setAttribute('aria-valuenow',String(percent)); track.firstElementChild.style.width=percent+'%'; }
        },
        finish() { dialog?.close(); document.querySelector('main')?.removeAttribute('aria-busy'); },
        paint,
    };
})();
