<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>PayPal trial diagnostics<?= $this->endSection() ?>
<?= $this->section('head') ?><meta name="robots" content="noindex, nofollow"><?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section admin-page">
<a href="<?= site_url('admin/users/'.$userId) ?>">← Back to member</a>
<h1>PayPal trial diagnostics</h1>
<p><?= esc($report['summary']) ?></p>
<p>Saved status: <strong><?= esc($report['local_status']) ?></strong> · PayPal status: <strong><?= esc($report['remote_status']??'Unavailable') ?></strong> · Environment: <?= esc($report['environment']) ?></p>
<p>Read-only inspection. Complimentary access is separate and does not affect these checks. No credentials, payer IDs, or payment details are included.</p>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Trial check</th><th>Result</th><th>Details</th></tr></thead><tbody>
<?php foreach ($report['checks'] as $check): ?><tr><td><?= esc($check['check']) ?></td><td><?= $check['passed']?'Pass':'Needs attention' ?></td><td><?= esc($check['detail']) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<h2>Safe diagnostic report</h2><p>Copy this report when requesting support. Times are UTC. A missing event time means no verified event timestamp has been saved for this agreement.</p>
<pre style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px"><?= esc(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre>
</section>
<?= $this->endSection() ?>
