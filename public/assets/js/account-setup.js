(() => {
    'use strict';
    const input = document.getElementById('setup-token');
    const token = window.location.hash.slice(1) || input.value;
    // Some embedded email browsers restrict history updates. That must not disable the form.
    try { history.replaceState(null, '', window.location.pathname); } catch (_) {}
    if (!/^[a-f0-9]{64}$/.test(token)) {
        document.getElementById('setup-link-error').hidden = false;
        return;
    }
    input.value = token;
    document.getElementById('setup-submit').disabled = false;
    const password = document.getElementById('password');
    const confirm = document.getElementById('password_confirm');
    function validateMatch() {
        confirm.setCustomValidity(confirm.value && confirm.value !== password.value
            ? 'Enter the same password in both fields.' : '');
    }
    [password, confirm].forEach(field => {
        field.addEventListener('input', validateMatch);
        field.addEventListener('change', validateMatch);
    });
    // Click, touch and Enter all use native form submission and the same validation.
    document.getElementById('account-setup-form').addEventListener('submit', event => {
        validateMatch();
        if (!event.currentTarget.reportValidity()) event.preventDefault();
    });
})();
