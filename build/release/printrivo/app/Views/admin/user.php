<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Manage member<?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section admin-page">
<a href="<?= site_url('admin') ?>">← All users</a><p class="eyebrow">MEMBER #<?= $user['id'] ?> · <?= esc(strtoupper(config('PayPal')->environment)) ?> BILLING</p>
<h1><?= esc(trim($user['first_name'].' '.$user['last_name'])) ?></h1><p><?= esc($user['email']??'No email') ?></p>
<?php if (session('admin_message')): ?><p role="status" class="alpha-offer"><?= esc(session('admin_message')) ?></p><?php endif ?>
<?php if (session('admin_error')): ?><p role="alert" class="alpha-offer"><?= esc(session('admin_error')) ?></p><?php endif ?>
<div class="account-panel"><h2>Subscription</h2>
<?php if ($subscription): ?>
<dl class="admin-details"><dt>PayPal agreement</dt><dd><?= esc($subscription['provider_subscription_id']??'Pending') ?></dd><dt>Status</dt><dd><?= esc($subscription['status']) ?><?= $subscription['cancel_at_period_end']?' · Renewal canceled':'' ?></dd><dt>Current period ends (UTC)</dt><dd><?= esc($subscription['current_period_end']??'Not confirmed') ?></dd></dl>
<?php if ($subscription['provider_subscription_id'] && !$subscription['cancel_at_period_end'] && !in_array($subscription['status'],['canceled','expired'],true)): ?>
<h3>Cancel at the member’s request</h3><p>This stops future renewals in PayPal. It does not refund payments or delete the account. Remaining confirmed trial or paid access continues until its end date.</p>
<form method="post" action="<?= site_url('admin/users/'.$user['id'].'/cancel') ?>">
<?= csrf_field() ?><input type="hidden" name="subscription_id" value="<?= $subscription['id'] ?>">
<label class="admin-confirm"><input type="checkbox" name="confirm" value="1" required> I verified this member’s request and want to cancel this subscription.</label>
<button class="button" type="submit">Confirm subscription cancellation</button></form>
<?php else: ?><p>No renewal cancellation is available for this agreement.</p><?php endif ?>
<?php else: ?><p>This user has no PayPal subscription in the current billing environment.</p><?php endif ?>
</div>
<h2>Recent admin actions</h2><p>Times are UTC. An unconfirmed request may have reached PayPal; verify its status before retrying.</p>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Admin ID</th><th>Subscription ID</th><th>Cancellation outcome</th></tr></thead><tbody>
<?php foreach ($actions as $action): ?><tr><td><?= esc($action['created_at']) ?></td><td><?= $action['admin_id'] ?></td><td><?= $action['subscription_id'] ?></td><td><?= esc($action['outcome']) ?></td></tr><?php endforeach ?>
<?php if (!$actions): ?><tr><td colspan="4">No admin actions recorded.</td></tr><?php endif ?>
</tbody></table></div></section>
<?= $this->endSection() ?>
