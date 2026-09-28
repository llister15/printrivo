<details class="app-launcher">
  <summary aria-label="Open app launcher" title="Your apps">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <?php foreach ([5,12,19] as $y): ?><?php foreach ([5,12,19] as $x): ?><circle cx="<?= $x ?>" cy="<?= $y ?>" r="1.8"/><?php endforeach ?><?php endforeach ?>
    </svg>
  </summary>
  <div class="app-launcher-panel">
    <p class="app-launcher-title">Your Printrivo apps</p>
    <ul class="app-launcher-grid">
      <?php foreach (config('Suite')->apps as $app): ?>
      <li><a href="<?= site_url($app['route']) ?>"><span class="app-launcher-icon" aria-hidden="true"><?= esc($app['icon']) ?></span><span><?= esc($app['shortName']) ?></span></a></li>
      <?php endforeach ?>
    </ul>
    <a class="app-launcher-dashboard" href="<?= site_url('dashboard') ?>">View dashboard <span aria-hidden="true">→</span></a>
  </div>
</details>
