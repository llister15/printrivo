(() => {
    'use strict';
    const access = document.getElementById('access');
    const paid = document.querySelector('[data-access-paid]');
    const free = document.querySelector('[data-access-free]');
    function update() {
        const isPaid = access.value === 'paid';
        paid.hidden = paid.disabled = !isPaid;
        free.hidden = free.disabled = isPaid;
    }
    access.addEventListener('change', update);
    update();
})();
