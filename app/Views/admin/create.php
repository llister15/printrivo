<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Create member<?= $this->endSection() ?>
<?= $this->section('head') ?><link rel="stylesheet" href="<?= base_url('assets/css/admin-members.css') ?>"><?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section admin-create"><div class="auth-card">
<a href="<?= site_url('admin') ?>">← All users</a><h1>Create a member</h1>
<p>The member sets their own password with a private invitation link. This creates an ordinary member account.</p>
<?php if ($error=session('admin_error')): ?><p class="form-alert" role="alert"><?= esc($error) ?></p><?php endif ?>
<form method="post" action="<?= site_url('admin/users/create') ?>">
<?= csrf_field() ?>
<label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="80" required>
<label for="last_name">Last name</label><input id="last_name" name="last_name" maxlength="80" required>
<label for="email">Email</label><input id="email" name="email" type="email" maxlength="254" required>
<label for="access">Access</label><select id="access" name="access"><option value="paid">Paid monthly subscription</option><option value="internal">Free internal access</option><option value="demo">Free demo access</option></select>
<fieldset data-access-paid><label for="amount">Agreed monthly price in USD</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" max="9.99" value="9.99" required>
<p>Paid members review these terms and authorize PayPal themselves. Saving a price does not charge them or grant paid access. Eligible new members can still receive the introductory trial.</p>
</fieldset><fieldset data-access-free><label for="days">Days of free access</label><input id="days" name="days" type="number" min="1" max="3650" value="365" required><p>No payment information is needed. Access begins today and expires automatically.</p></fieldset>
<label class="admin-confirm"><input name="send_invitation" type="checkbox" value="1" checked> Email the setup link and access terms to this member.</label>
<p>A private link is also shown after creation so you can share it if email is unavailable.</p>
<button class="button" type="submit">Create member</button></form></div></section>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= base_url('assets/js/admin-members.js') ?>"></script><?= $this->endSection() ?>
