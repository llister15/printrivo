<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Your subscription<?= $this->endSection() ?>
<?= $this->section('head') ?><meta name="robots" content="noindex, nofollow"><?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="container section"><div class="account-panel">
<p class="eyebrow">YOUR PRINTRIVO ACCOUNT</p>
<?php $complimentary = $subscription && in_array($subscription['provider'], \App\Services\ComplimentaryAccess::PROVIDERS, true); ?>
<?php if (!$subscription): ?><p class="eyebrow">STEP 2 OF 2 · PAYMENT SETUP</p><?php endif ?>
<h1><?= $complimentary ? 'Your complimentary access is active.' : ($subscription ? ($subscription['status']==='trialing' ? 'Your 14-day trial is active.' : 'Your subscription is active.') : 'Your account is ready.') ?></h1>
<?php if ($message=session('billing_message')): ?><p class="form-message" role="status"><?= esc($message) ?></p><?php endif ?>
<?php if ($error=session('billing_error')): ?><p class="form-alert" role="alert"><?= esc($error) ?></p><?php endif ?>
<?php if ($paypalReady && config('PayPal')->environment==='sandbox'): ?>
<div class="signup-notice"><strong>PayPal sandbox testing</strong><p>Use a PayPal sandbox buyer account. This checkout is for testing and does not take real payments.</p></div>
<?php endif ?>
<?php if ($subscription): ?>
<p>You have access to all three tools through <?= esc(gmdate('F j, Y', strtotime($subscription['current_period_end'].' UTC'))) ?>.</p>
<?php if ($complimentary): ?><p><?= esc(ucfirst($subscription['provider'])) ?> access was provided by Printrivo. No payment information is required for this access, and it does not renew or charge automatically.</p><?php endif ?>
<?php if ($subscription['status']==='trialing' && !$subscription['cancel_at_period_end'] && !$subscription['discount_amount']): ?><p>Your first $<?= number_format($subscription['billing_amount']/100,2) ?> USD monthly payment is scheduled for <?= esc(gmdate('F j, Y, H:i', strtotime($subscription['trial_ends_at'].' UTC'))) ?> UTC. Cancel before that time to avoid the charge.</p><?php endif ?>
<?php if ($subscription['cancel_at_period_end']): ?><p>Renewal is canceled. Your access ends on that date.</p><?php endif ?>
<?php else: ?>
<p>The monthly plan is $9.99 USD/month for all three tools.</p>
<?php if ($offer['custom']): ?><p><strong>Your agreed price:</strong> $<?= number_format($offer['amount']/100,2) ?> USD/month while this subscription continues. A replacement subscription requires a new offer.</p><?php elseif ($alphaEligible): ?><p><strong>Alpha code saved:</strong> your early-adopter offer is $4.99 USD/month while continuously subscribed.</p><?php endif ?>
<?php if (!$paypal || in_array($paypal['status'],['incomplete','canceled','expired'],true)): ?>
<p>Unlock the Gang Sheet Builder, Image Studio, and QR Generator. All future Printrivo tools are included while subscribed, with no upgrade costs.</p>
<?php if ($trialEligible): ?><p><strong>14 days free today.</strong> Your trial starts when PayPal confirms your subscription. After 14 days, $<?= number_format($offer['amount']/100,2) ?> USD/month is charged automatically unless you cancel. Cancel anytime; access lasts through your trial or paid month.</p>
<?php elseif (!config('PayPal')->trialsReady()): ?><p>Trial checkout is not configured yet. The paid option below starts monthly billing immediately after approval; it does not include a free trial.</p>
<?php elseif (!$paypal || !$paypal['trial_requested']): ?><p>A free trial is not available for this account or device, or the network has reached its daily trial limit. You can choose a paid subscription, try again later if this is a shared network, or contact support for an eligibility review.</p><?php endif ?>
<form method="post" action="<?= site_url('billing/paypal/checkout') ?>">
<?= csrf_field() ?>
<input type="hidden" name="price_offer" value="<?= $offer['amount'] ?>">
<input type="hidden" name="trial_offer" value="<?= $trialEligible ? '1' : '0' ?>">
<label class="billing-consent"><input type="checkbox" name="billing_consent" value="1" required> I agree to the <a href="<?= site_url('terms') ?>">subscription terms</a> and authorize automatic payments of $<?= number_format($offer['amount']/100,2) ?> USD/month<?= ($trialEligible || ($paypal && $paypal['trial_requested'] && $paypal['status']==='incomplete')) ? ' after my 14-day free trial' : ' starting upon subscription approval' ?> until I cancel. Cancellation stops future renewals.</label>
<button class="button" type="submit"><?= $paypal && $paypal['status']==='incomplete' ? 'Continue with PayPal' : ($trialEligible ? 'Start 14-day free trial with PayPal' : 'Purchase subscription for $' . (number_format($offer['amount']/100,2)) . '/month') ?> <span aria-hidden="true">↗</span></button>
</form>
<?php if ($paypalReady): ?><p>Pay securely with PayPal. Review your recurring monthly amount before approving, and cancel renewal anytime.</p><?php endif ?>
<?php endif ?>

<?php endif ?>
<?php if ($paypal): ?>
<div class="billing-details"><h2>PayPal subscription</h2><p>Status: <strong><?= esc($paypal['status']==='active' && $paypal['cancel_at_period_end'] ? 'Renewal canceled' : ucfirst($paypal['status'])) ?></strong></p>
<p>Monthly price: $<?= number_format($paypal['billing_amount']/100,2) ?> USD.</p>
<?php if ($paypal['discount_amount']): ?>
<p><?= $paypal['discount_confirmed_at'] ? 'PayPal has confirmed your discounted monthly price of' : 'A discounted monthly price is awaiting PayPal confirmation:' ?> $<?= number_format($paypal['discount_amount']/100,2) ?> USD. Payments due within 10 days of the confirmed change may still use the previous price. Your billing schedule stays the same; no refund or new trial is created.</p>
<?php endif ?>
<?php if (!$subscription && !in_array($paypal['status'],['canceled','expired'],true)): ?><p>We confirm your trial or payment directly with PayPal before activating access. A previously used PayPal payer cannot start another free trial.</p><?php endif ?>
<?php if ($paypalReady && $paypal['provider_subscription_id']): ?>
<form method="post" action="<?= site_url('billing/paypal/refresh') ?>"><?= csrf_field() ?><button class="button compact" type="submit">Check payment status</button></form>
<?php if (!$paypal['cancel_at_period_end'] && !in_array($paypal['status'],['canceled','expired'],true)): ?>
<details class="billing-cancel"><summary>Cancel PayPal renewal</summary><p>This stops future recurring payments. Confirmed trial or paid access continues to the end of the current period. After a paid Alpha subscription is canceled, a replacement subscription uses the standard price.</p><form method="post" action="<?= site_url('billing/paypal/cancel') ?>"><?= csrf_field() ?><button class="button compact" type="submit">Confirm cancellation</button></form><p>If security verification is unavailable, you can also cancel through the automatic-payment settings in your PayPal account.</p></details>
<?php endif ?>
<?php endif ?></div>
<?php endif ?>
<section class="billing-history" aria-labelledby="billing-history-title">
<h2 id="billing-history-title">Billing history</h2>
<p class="billing-history-intro">Review your past subscription payments, including earlier subscriptions.</p>
<div id="billing-history-content" data-history-url="<?= site_url('account/billing-history') ?>" aria-live="polite">
<a class="text-link" href="<?= site_url('account/billing-history') ?>">View billing history →</a>
</div>
</section>
<a class="text-link" href="<?= site_url('dashboard') ?>">Back to dashboard <span aria-hidden="true">→</span></a>
</div></section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?><script src="<?= base_url('assets/js/billing-history.js') ?>"></script><?= $this->endSection() ?>
