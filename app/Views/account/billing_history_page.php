<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Billing history<?= $this->endSection() ?>
<?= $this->section('head') ?><meta name="robots" content="noindex, nofollow"><?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section"><div class="account-panel"><h1>Billing history</h1><?= view('account/billing_history',['history'=>$history]) ?><p><a class="text-link" href="<?= site_url('account/subscription') ?>">Back to subscription →</a></p></div></section>
<?= $this->endSection() ?>
