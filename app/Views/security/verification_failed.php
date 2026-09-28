<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Security verification<?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section"><div class="account-panel"><h1>Please try again</h1><p role="alert"><?= esc($message) ?></p><p>Use your browser’s Back button to return to the form, then submit again for a fresh verification. Your request was not processed.</p><p>To stop subscription renewal if verification is unavailable, you can also cancel through the automatic-payment settings in your PayPal account.</p><a href="<?= site_url('account/subscription') ?>">Subscription and cancellation</a> · <a href="<?= site_url('login') ?>">Log in</a></div></section>
<?= $this->endSection() ?>
