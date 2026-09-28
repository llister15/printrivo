<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Set up your account<?= $this->endSection() ?>
<?= $this->section('head') ?><meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer"><?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section account-setup"><div class="auth-card"><h1>Set your password</h1>
<p id="password-help">Complete your Printrivo invitation. Choose a password with at least 12 characters.</p>
<?php if ($error=$setupError ?? session('setup_error')): ?><p class="form-alert" role="alert"><?= esc($error) ?></p><?php endif ?>
<p id="setup-link-error" class="form-alert" role="alert" hidden>Open the complete setup link from your invitation. If it has expired, ask your administrator for a new one.</p>
<noscript><p>Enable JavaScript to securely use your invitation link.</p></noscript>
<form id="account-setup-form" method="post" action="<?= site_url('account/setup') ?>">
<?= csrf_field() ?><input type="hidden" name="token" id="setup-token" value="<?= esc($setupToken ?? '', 'attr') ?>">
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password-help" minlength="12" maxlength="255" required>
<label for="password_confirm">Confirm password</label><input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="12" maxlength="255" required>
<button class="button full" id="setup-submit" type="submit" disabled>Set password</button>
</form></div></section>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= base_url('assets/js/account-setup.js?v=20260923-password') ?>"></script><?= $this->endSection() ?>
