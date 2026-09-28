<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Admin dashboard<?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section admin-page">
<p class="eyebrow">PRINTRIVO ADMIN · <?= esc(strtoupper(config('PayPal')->environment)) ?> BILLING</p>
<h1>Member overview</h1><p>Manage your members and respond to subscription requests.</p>
<div class="admin-stats">
<article class="account-panel"><span>Active members</span><strong><?= $stats['active'] ?></strong><p>Accounts with current tool access, including trials and canceled renewals with time remaining.</p></article>
<article class="account-panel"><span>Total users</span><strong><?= $stats['total'] ?></strong><p>Registered accounts, excluding deleted accounts.</p></article>
<article class="account-panel"><span>Seen in the last 24 hours</span><strong><?= $stats['recent'] ?></strong><p>Based on recorded account activity. This is not a live online count.</p></article>
</div>
<h2>All users</h2>
<form class="admin-search" method="get" action="<?= site_url('admin') ?>"><label for="q">Search by name or email</label><div><input id="q" name="q" maxlength="100" value="<?= esc($q,'attr') ?>" type="search"><button class="button compact">Search</button><a href="<?= site_url('admin') ?>">Clear</a></div></form>
<p><?= $count ?> matching users · Page <?= $page ?> of <?= max(1,(int)ceil($count/25)) ?></p>
<div class="admin-table-wrap" tabindex="0" role="region" aria-label="Members table; scroll horizontally on small screens">
<table class="admin-table"><thead><tr><th>Member</th><th>Tool access</th><th>Subscription</th><th>Last activity (UTC)</th><th>Action</th></tr></thead><tbody>
<?php foreach ($users as $user): $sub=$user['subscription']; ?>
<tr><td><strong><?= esc(trim($user['first_name'].' '.$user['last_name'])) ?></strong><br><?= esc($user['email']??'No email') ?><br><small>Joined <?= esc($user['created_at']) ?></small></td><td><?= $user['access']?'Active':'No access' ?></td><td><?= esc($sub ? ($sub['cancel_at_period_end']?'Renewal canceled':ucfirst($sub['status'])) : 'No PayPal subscription') ?><?php if ($sub && $sub['current_period_end']): ?><br><small>Period ends <?= esc($sub['current_period_end']) ?> UTC</small><?php endif ?></td><td><?= esc($user['last_active']??'Not recorded') ?></td><td><a href="<?= site_url('admin/users/'.$user['id']) ?>">Manage<span class="sr-only"> <?= esc($user['email']??'user') ?></span> →</a></td></tr>
<?php endforeach ?>
<?php if (!$users): ?><tr><td colspan="5">No users match your search.</td></tr><?php endif ?>
</tbody></table></div>
<nav class="admin-pagination" aria-label="User pages"><?php if ($page>1): ?><a href="<?= site_url('admin').'?'.esc(http_build_query(['q'=>$q,'page'=>$page-1]),'attr') ?>">← Previous</a><?php endif ?><?php if ($page*25<$count): ?><a href="<?= site_url('admin').'?'.esc(http_build_query(['q'=>$q,'page'=>$page+1]),'attr') ?>">Next →</a><?php endif ?></nav>
</section>
<?= $this->endSection() ?>
