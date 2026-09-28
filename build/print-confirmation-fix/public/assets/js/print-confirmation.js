'use strict';
window.PrintOrderConfirmation = (() => {
    const dialog = document.getElementById('print-order-confirmation');
    const form = dialog.querySelector('form');
    const delivery = form.elements.fulfillment;
    const address = form.elements.shipping_address;
    const shipping = dialog.querySelector('[data-shipping]');
    const price = (width, height, dpi) => Math.max(1500, Math.round(width * height * 3 / (dpi * dpi)));
    delivery.onchange = () => {
        shipping.hidden = delivery.value !== 'shipping';
        address.required = !shipping.hidden;
    };
    dialog.addEventListener('keydown', event => event.stopPropagation());
    function open({width, height, dpi}) {
        if (![width, height, dpi].every(n => Number.isFinite(Number(n)) && Number(n) > 0)) {
            return Promise.reject(new Error('Choose valid print dimensions and DPI.'));
        }
        const cents = price(width, height, dpi);
        dialog.querySelector('[data-dimensions]').textContent = `${(width / dpi).toFixed(2)} × ${(height / dpi).toFixed(2)} inches · ${(width * height / dpi / dpi).toFixed(2)} sq in · 1 copy`;
        dialog.querySelector('[data-price]').textContent = new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(cents / 100);
        delivery.onchange();
        return new Promise(resolve => {
            let result = null;
            form.onsubmit = event => {
                event.preventDefault();
                if (!form.reportValidity()) return;
                if (delivery.value === 'shipping' && address.value.trim().length < 10) {
                    address.setCustomValidity('Enter the recipient and complete shipping address.');
                    address.reportValidity(); return;
                }
                result = {fulfillment:delivery.value, shipping_address:delivery.value === 'shipping' ? address.value.trim() : '', confirmed_price_cents:String(cents)};
                dialog.close();
            };
            address.oninput = () => address.setCustomValidity('');
            dialog.querySelector('[data-cancel]').onclick = () => dialog.close();
            dialog.addEventListener('close', () => { form.onsubmit = null; resolve(result); }, {once:true});
            dialog.showModal();
        });
    }
    return {open, price};
})();
