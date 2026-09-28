<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Your dashboard<?= $this->endSection() ?>
<?= $this->section('head') ?><meta name="robots" content="noindex, nofollow"><?= $this->endSection() ?>
<?= $this->section('body_class') ?>dashboard-page<?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container dashboard-section">
  <div class="dashboard-heading">
    <div><p class="eyebrow">YOUR PRINT WORKSPACE</p><h1>Your dashboard</h1><p class="lead">Welcome, <?= esc(auth()->user()->first_name) ?>. Ready for your next project?</p></div>
  </div>
  <section class="production-guide" aria-labelledby="production-guide-title">
    <div class="production-guide-heading">
      <p class="eyebrow">YOUR FIRST 14 DAYS</p>
      <h2 id="production-guide-title">From design to your first production run.</h2>
      <p>Start with one real project. Follow these four steps to prepare your artwork, plan production, and price your work with confidence.</p>
    </div>
    <ol class="production-steps">
      <li>
        <span class="production-step-days">Days 1–3</span>
        <h3>Prepare your design</h3>
        <p>Choose artwork you own or have permission to sell. Clean it up in Image Studio and check the size for your product.</p>
      </li>
      <li>
        <span class="production-step-days">Days 4–7</span>
        <h3>Plan your production</h3>
        <p>Arrange your designs in the Gang Sheet Builder. Check spacing and print a sample before starting a full run.</p>
      </li>
      <li>
        <span class="production-step-days">Days 8–10</span>
        <h3>Price and present</h3>
        <p>Add up materials, labor, packaging, and fees. Set a price that covers your costs and target margin, then photograph your sample.</p>
      </li>
      <li>
        <span class="production-step-days">Days 11–14</span>
        <h3>Make reordering easy</h3>
        <p>Create a QR code for your shop or order form. Save your files and job notes to make the next order faster.</p>
      </li>
    </ol>
    <div class="production-guide-footer">
      <p><strong>More tools. No upgrade costs.</strong> All future Printrivo tools are included while you stay subscribed.</p>
    </div>
    <p class="production-guide-note">Sales and earnings depend on your products, pricing, and customers.</p>
  </section>
  <div class="dashboard-tools-heading"><h2>Available tools</h2><p>Everything you need to prepare your next print.</p></div>
  <div class="feature-grid dashboard-tools">
    <?php foreach (config('Suite')->apps as $index => $app): ?>
      <article class="feature-card">
        <div class="feature-number"><?= esc(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?><span><?= esc($app['label']) ?></span></div>
        <a class="dashboard-preview" href="<?= site_url($subscription ? $app['route'] : 'account/subscription') ?>" aria-label="<?= esc($app['action'], 'attr') ?>"><img src="<?= base_url('assets/img/' . rawurlencode($app['preview'])) ?>" alt="<?= esc($app['name'], 'attr') ?> screenshot" width="1440" height="960" loading="lazy" decoding="async"></a>
        <h3><?= esc($app['name']) ?></h3><p><?= esc($app['description']) ?></p>
        <a class="button tool-launch" href="<?= site_url($subscription ? $app['route'] : 'account/subscription') ?>"><?= esc($subscription ? $app['action'] : 'Subscription required') ?> <span aria-hidden="true">→</span></a>
      </article>
    <?php endforeach ?>
  </div>
  <section class="dashboard-membership" aria-label="Subscription status">
    <div><span class="pill"><?= $subscription ? ($subscription['status']==='trialing' ? '14-day trial active' : 'Subscription active') : 'Subscription not active' ?></span><h2><?= $subscription ? 'Your tools are ready.' : 'Your account is ready.' ?></h2><p><?= $subscription ? 'All three tools are ready, and future Printrivo tools are included while you stay subscribed—no upgrade costs.' : 'Finish payment setup to unlock your tools. Eligible new members get 14 days free, then $9.99/month. Cancel anytime.' ?></p></div>
    <a class="button compact" href="<?= site_url('account/subscription') ?>"><?= $subscription ? 'Manage subscription' : 'Finish payment setup' ?> <span aria-hidden="true">→</span></a>
  </section>
</section>
<?= $this->endSection() ?>
