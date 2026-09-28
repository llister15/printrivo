<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Manage member<?= $this->endSection() ?>
<?= $this->section('head') ?><link rel="stylesheet" href="<?= base_url('assets/css/admin-members.css') ?>"><?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section admin-page">
<a href="<?= site_url('admin') ?>">← All users</a><p class="eyebrow">MEMBER #<?= $user['id'] ?> · <?= esc(strtoupper(config('PayPal')->environment)) ?> BILLING</p>
<h1><?= esc(trim($user['first_name'].' '.$user['last_name'])) ?></h1><p><?= esc($user['email']??'No email') ?></p>
<?php if (session('admin_message')): ?><p role="status" class="alpha-offer"><?= esc(session('admin_message')) ?></p><?php endif ?>
<?php if (session('admin_error')): ?><p role="alert" class="alpha-offer"><?= esc(session('admin_error')) ?></p><?php endif ?>
<?php $setup=session('member_setup'); if ($setup && $setup['user_id']===(int)$user['id']): ?>
<div class="account-panel"><h2>Private password setup link</h2><p>Share only with this member. It expires in 7 days and can be used once. This link is shown only after creation or reissue.</p>
<label for="setup-link">Setup link</label><input id="setup-link" type="text" readonly value="<?= esc($setup['link'],'attr') ?>"></div>
<?php endif ?>
<?php if ($invitation && !$invitation['accepted_at']): ?>
<div class="account-panel"><h2>Pending account setup</h2><p>Current invitation expires <?= esc($invitation['expires_at']) ?> UTC.</p>
<form method="post" action="<?= site_url('admin/users/'.$user['id'].'/invite') ?>"><?= csrf_field() ?>
<label class="admin-confirm"><input type="checkbox" name="send_invitation" value="1" checked> Email the replacement invitation.</label>
<button class="button compact" type="submit">Create new setup link</button></form></div>
<?php endif ?>
<div class="account-panel"><h2>Demo and internal access</h2>
<p>Grant access to all tools without payment information. This does not grant administrator permissions.</p>
<?php if ($complimentary): ?><p><strong><?= esc(ucfirst($complimentary['provider'])) ?> access</strong> through <?= esc($complimentary['current_period_end']) ?> UTC.</p><?php else: ?><p>No current complimentary access.</p><?php endif ?>
<form method="post" action="<?= site_url('admin/users/'.$user['id'].'/access') ?>">
<?= csrf_field() ?>
<label for="access-kind">Access type</label><select id="access-kind" name="kind"><option value="demo">Demo</option><option value="internal">Internal</option></select>
<label for="access-days">Days of access (1–3650)</label><input id="access-days" name="days" type="number" min="1" max="3650" value="30" required>
<p>Access expires automatically. A new grant replaces existing complimentary access from today. Existing PayPal billing continues until separately canceled.</p>
<button class="button" type="submit">Grant complimentary access</button></form>
<?php if ($complimentary): ?><form method="post" action="<?= site_url('admin/users/'.$user['id'].'/access') ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="revoke"><button class="button compact" type="submit">Revoke complimentary access</button></form><?php endif ?>
</div>
<div class="account-panel"><h2>Subscription</h2>
<p><a href="<?= site_url('admin/users/'.$user['id'].'/paypal-diagnostics') ?>">Diagnose PayPal trial status →</a></p>
<?php if ($subscription): ?>
<dl class="admin-details"><dt>PayPal agreement</dt><dd><?= esc($subscription['provider_subscription_id']??'Pending') ?></dd><dt>Status</dt><dd><?= esc($subscription['status']) ?><?= $subscription['cancel_at_period_end']?' · Renewal canceled':'' ?></dd><dt>Current period ends (UTC)</dt><dd><?= esc($subscription['current_period_end']??'Not confirmed') ?></dd></dl>
<p>Monthly amount: $<?= number_format($subscription['billing_amount']/100,2) ?> USD.</p>
<form method="post" action="<?= site_url('admin/users/'.$user['id'].'/refresh') ?>"><?= csrf_field() ?><button class="button compact">Check PayPal status</button></form>
<?php if ($subscription['discount_amount']): ?><p>Discount to $<?= number_format($subscription['discount_amount']/100,2) ?> USD/month: <strong><?= $subscription['discount_confirmed_at']?'Confirmed by PayPal':'Confirmation pending' ?></strong>.</p><?php endif ?>
<?php if (!$subscription['cancel_at_period_end'] && in_array($subscription['status'],['active','trialing'],true)): ?>
<h3>Discount this subscription</h3>
<p>This changes only this member’s recurring price. Payments due within 10 days may keep the old price. It does not refund prior payments, change the billing date, or start another trial. Another discount can be applied after the previous one appears on a payment.</p>
<form method="post" action="<?= site_url('admin/users/'.$user['id'].'/discount') ?>"><?= csrf_field() ?><input type="hidden" name="subscription_id" value="<?= $subscription['id'] ?>">
<?php if ($subscription['discount_amount'] && !$subscription['discount_confirmed_at']): ?>
<input type="hidden" name="retry" value="1"><p>Retry the existing $<?= number_format($subscription['discount_amount']/100,2) ?> discount after checking its status. No different price will be submitted.</p>
<?php else: ?><label for="discount-amount">New monthly price in USD</label><input id="discount-amount" name="amount" type="number" step="0.01" min="0.01" max="<?= number_format(($subscription['billing_amount']-1)/100,2,'.','') ?>" required><?php endif ?>
<label class="admin-confirm"><input type="checkbox" name="confirm" value="1" required> Apply this discount to the member’s existing PayPal agreement.</label>
<label class="admin-confirm"><input type="checkbox" name="notify" value="1" checked> Email the member when PayPal confirms.</label>
<button class="button" type="submit">Apply discount</button></form>
<?php endif ?>
<?php if ($subscription['provider_subscription_id'] && !$subscription['cancel_at_period_end'] && !in_array($subscription['status'],['canceled','expired'],true)): ?>
<h3>Cancel at the member’s request</h3><p>This stops future renewals in PayPal. It does not refund payments or delete the account. Remaining confirmed trial or paid access continues until its end date.</p>
<form method="post" action="<?= site_url('admin/users/'.$user['id'].'/cancel') ?>">
<?= csrf_field() ?><input type="hidden" name="subscription_id" value="<?= $subscription['id'] ?>">
<label class="admin-confirm"><input type="checkbox" name="confirm" value="1" required> I verified this member’s request and want to cancel this subscription.</label>
<button class="button" type="submit">Confirm subscription cancellation</button></form>
<?php else: ?><p>No renewal cancellation is available for this agreement.</p><?php endif ?>
<?php else: ?><p>This user has no PayPal subscription in the current billing environment.</p><?php endif ?>
</div>
<?php if (!$subscription || in_array($subscription['status'],['canceled','expired'],true)): ?>
<div class="account-panel"><h2>Agreed price for a new subscription</h2>
<?php if ($price): ?><p>Saved offer: $<?= number_format($price['amount']/100,2) ?> USD/month.</p><?php endif ?>
<p>Save a member-specific price before they start checkout. It lasts while that subscription continues. This is a subscription offer, not a paid invoice. Free accounts use the complimentary access controls above.</p>
<form method="post" action="<?= site_url('admin/users/'.$user['id'].'/price') ?>"><?= csrf_field() ?>
<label for="offer-amount">Monthly price in USD</label><input id="offer-amount" name="amount" type="number" step="0.01" min="0.01" max="9.99" value="<?= number_format(($price['amount']??999)/100,2,'.','') ?>" required>
<label class="admin-confirm"><input type="checkbox" name="notify" value="1"> Email this subscription offer to the member.</label>
<button class="button" type="submit">Save agreed price</button></form></div>
<?php endif ?>
<h2>Recent admin actions</h2><p>Times are UTC. An unconfirmed request may have reached PayPal; verify its status before retrying.</p>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Admin ID</th><th>Subscription ID</th><th>Action</th><th>Outcome</th></tr></thead><tbody>
<?php foreach ($actions as $action): ?><tr><td><?= esc($action['created_at']) ?></td><td><?= $action['admin_id'] ?></td><td><?= $action['subscription_id'] ?></td><td><?= esc($action['action']) ?></td><td><?= esc($action['outcome']) ?></td></tr><?php endforeach ?>
<?php if (!$actions): ?><tr><td colspan="5">No admin actions recorded.</td></tr><?php endif ?>
</tbody></table></div></section>
<?= $this->endSection() ?>
