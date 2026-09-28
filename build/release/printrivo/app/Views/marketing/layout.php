<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#202c40">
  <title><?= $this->renderSection('title') ?> | Printrivo</title>
  <meta name="description" content="Build DTF gang sheets, prepare print artwork and create custom QR codes with Printrivo. Discover the $9.99/month monthly plan from Mber Digital.">
  <link rel="icon" href="<?= base_url('assets/img/printrivo_light_logo.png') ?>" type="image/png">
  <link rel="stylesheet" href="<?= base_url('assets/css/marketing.css?v=20260920-analytics') ?>">
  <?= $this->renderSection('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/back-to-top.css') ?>">
</head>
<?php $bodyClass = trim($this->renderSection('body_class')); ?>
<body class="<?= esc($bodyClass) ?>">
<?php if (!auth()->loggedIn()): ?>
<aside class="promo-banner" aria-label="Early-adopter offer" id="alpha-banner"><div class="container promo-banner-row"><p><strong>Early adopter offer</strong> · Use code <strong>Alpha</strong> at registration to save your early-adopter discount. Regular plan: $9.99/month.</p><button type="button" id="dismiss-alpha" aria-label="Dismiss early-adopter offer"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div></aside>
<?php endif ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container nav-row">
    <a class="wordmark" href="<?= site_url('/') ?>" aria-label="Printrivo home">
      <img src="<?= base_url('assets/img/printrivo_dark_logo.png') ?>" width="58" height="49" alt="Printrivo logo">
    </a>
    <nav aria-label="Main navigation">
      <?php if (auth()->loggedIn()): ?>
        <?php if (auth()->user()->inGroup('admin')): ?><a href="<?= site_url('admin') ?>">Admin</a><?php endif ?>
        <a href="<?= site_url('dashboard') ?>"<?= $bodyClass === 'dashboard-page' ? ' aria-current="page"' : '' ?>>Dashboard</a>
        <a href="<?= site_url('account/subscription') ?>">Subscription</a>
        <?php
          $profileUser = auth()->user();
          $profileName = trim((string) $profileUser->first_name);
          $profileInitial = mb_strtoupper(mb_substr($profileName !== '' ? $profileName : 'User', 0, 1));
          $avatarUrl = 'https://www.gravatar.com/avatar/' . hash('sha256', strtolower(trim((string) $profileUser->email))) . '?s=84&d=404&r=g';
        ?>
        <?php if ($profileUser->active && !$profileUser->isBanned() && (new \App\Services\SubscriptionAccess())->activeFor((int)$profileUser->id)): ?>
          <?= view('partials/app_launcher') ?>
        <?php endif ?>
        <details class="profile-menu">
          <summary aria-label="Open your account menu"><span class="profile-avatar" aria-hidden="true"><span class="profile-initial"><?= esc($profileInitial) ?></span><img class="profile-photo" data-avatar-src="<?= esc($avatarUrl, 'attr') ?>" alt="" width="42" height="42" referrerpolicy="no-referrer" hidden></span><span class="profile-chevron" aria-hidden="true">⌄</span></summary>
          <div class="profile-dropdown">
            <div class="profile-identity"><strong><?= esc(trim(auth()->user()->first_name . ' ' . auth()->user()->last_name)) ?></strong><span>Your Printrivo account</span></div>
            <a href="<?= site_url('account/subscription') ?>">Subscription status</a>
            <form method="post" action="<?= site_url('logout') ?>"><?= csrf_field() ?><button type="submit">Sign out <span aria-hidden="true">→</span></button></form>
          </div>
        </details>
      <?php else: ?>
        <a href="<?= site_url('/') ?>#tools">The tools</a>
        <a href="<?= site_url('pricing') ?>">Pricing</a>
        <a href="<?= site_url('login') ?>">Log in</a>
        <a class="button compact" href="<?= site_url('register') ?>">Start free trial <span aria-hidden="true">↗</span></a>
      <?php endif ?>
    </nav>
  </div>
</header>
<main id="main"><?= $this->renderSection('main') ?></main>
<footer class="site-footer">
  <div class="container footer-row">
    <div><strong>Printrivo</strong><p>Professional print-production tools from Mber Digital.</p><small>&copy; <?= date('Y') ?> Mber Digital</small></div>
    <nav aria-label="Footer navigation"><a href="<?= site_url('terms') ?>">Terms and Conditions</a><a href="<?= site_url('privacy') ?>">Privacy Policy</a><a href="<?= site_url('pricing') ?>">Pricing</a><a href="<?= site_url('login') ?>">Member login</a><a href="https://mberdigital.com/" target="_blank" rel="noopener noreferrer">Mber Digital <span aria-hidden="true">↗</span></a></nav>
  </div>
</footer>
<script>
const alphaBanner = document.getElementById('alpha-banner');
if (alphaBanner) {
  try { alphaBanner.hidden = localStorage.getItem('printrivo.alpha.dismissed') === '1'; } catch (_) {}
  document.getElementById('dismiss-alpha').addEventListener('click', () => {
    alphaBanner.hidden = true;
    try { localStorage.setItem('printrivo.alpha.dismissed', '1'); } catch (_) {}
  });
}

document.querySelectorAll('[data-avatar-src]').forEach(function (image) {
  image.addEventListener('load', function () { image.hidden = false; });
  image.addEventListener('error', function () { image.hidden = true; });
  image.src = image.dataset.avatarSrc;
});
document.addEventListener('click', function (event) {
  document.querySelectorAll('.profile-menu[open], .app-launcher[open]').forEach(function (menu) {
    if (!menu.contains(event.target)) menu.removeAttribute('open');
  });
});
document.querySelectorAll('.profile-menu, .app-launcher').forEach(function (menu) {
  menu.querySelector('summary').addEventListener('click', function () {
    document.querySelectorAll('.profile-menu[open], .app-launcher[open]').forEach(function (other) {
      if (other !== menu) other.removeAttribute('open');
    });
  });
});
document.addEventListener('keydown', function (event) {
  if (event.key !== 'Escape') return;
  document.querySelectorAll('.profile-menu[open], .app-launcher[open]').forEach(function (menu) {
    menu.removeAttribute('open');
    menu.querySelector('summary').focus();
  });
});
</script>
<?= $this->renderSection('scripts') ?>
<?= view('security/recaptcha') ?>
<?= view('partials/back_to_top') ?>
<?= view('partials/analytics') ?>
</body>
</html>
