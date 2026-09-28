<?php if (config('Recaptcha')->enabled): ?>
<meta name="recaptcha-site-key" content="<?= esc(config('Recaptcha')->siteKey,'attr') ?>">
<script src="<?= base_url('assets/js/recaptcha.js') ?>"></script>
<?php if (config('Recaptcha')->ready()): ?><script src="https://www.google.com/recaptcha/api.js?render=<?= rawurlencode(config('Recaptcha')->siteKey) ?>" async defer></script><?php endif ?>
<noscript><p>JavaScript is required for security verification before submitting forms.</p></noscript>
<?php endif ?>
