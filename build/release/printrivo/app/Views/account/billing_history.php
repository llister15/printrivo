<?php
$historyUrl=static function(?int $id, ?int $before=null): string {
    $params=[];
    if ($id!==null) { $params['subscription']=$id; }
    if ($before!==null) { $params['before']=$before; }
    return site_url('account/billing-history').($params?'?'.http_build_query($params):'');
};
?>
<?php if (!$history['subscriptions']): ?>
<p class="billing-history-empty">No billing history yet. Your subscription payments will appear here after PayPal records them. A free trial does not create a payment.</p>
<?php else: ?>
<?php if (count($history['subscriptions'])>1): ?>
<details class="history-agreements"><summary>Choose subscription</summary><ul>
<?php foreach ($history['subscriptions'] as $agreement): ?>
<li><a data-history-link href="<?= esc($historyUrl((int)$agreement['id']),'attr') ?>"<?= $agreement['id']===$history['selected']['id']?' aria-current="true"':'' ?>><?= esc($agreement['provider_subscription_id']) ?> · <?= esc(ucfirst($agreement['status'])) ?></a></li>
<?php endforeach ?>
</ul></details>
<?php endif ?>
<p class="billing-history-reference">Subscription <?= esc($history['selected']['provider_subscription_id']) ?><?php if (config('PayPal')->environment==='sandbox'): ?> · Sandbox test payments<?php endif ?></p>
<?php if ($history['error']): ?>
<p class="form-alert" role="alert"><?= esc($history['error']) ?></p>
<a data-history-link class="text-link" href="<?= esc($historyUrl((int)$history['selected']['id'],$history['end']),'attr') ?>">Try again →</a>
<?php else: ?>
<p class="billing-history-range"><?= esc(gmdate('M j, Y',$history['start']+1)) ?> – <?= esc(gmdate('M j, Y',$history['end'])) ?> <span>· Dates in UTC</span></p>
<?php if (!$history['transactions']): ?>
<p class="billing-history-empty">No payment transactions in this date range. Free trials and months without a recorded payment do not appear as charges.</p>
<?php else: ?>
<div class="billing-history-table-wrap" role="region" aria-label="Subscription payment history" tabindex="0">
<table class="billing-history-table">
<caption class="history-sr-only">PayPal subscription payments, newest first</caption>
<thead><tr><th scope="col">Payment date</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead>
<tbody>
<?php foreach ($history['transactions'] as $payment): ?>
<tr><td><time datetime="<?= esc(str_replace(' ','T',$payment['date']).'Z','attr') ?>"><?= esc(gmdate('M j, Y',strtotime($payment['date'].' UTC'))) ?></time><details class="history-payment-id"><summary>Transaction details</summary><span><?= esc($payment['id']) ?></span><span><?= esc($payment['date']) ?> UTC</span></details></td>
<td class="history-amount"><?= esc(number_format($payment['amount']/100,2)) ?> <small><?= esc($payment['currency']) ?></small></td>
<td><span class="history-status history-status-<?= esc($payment['tone'],'attr') ?>"><?= esc($payment['status']) ?></span></td></tr>
<?php endforeach ?>
</tbody></table></div>
<p class="billing-history-note">Amounts show the original payment, including any payment later refunded or reversed. Recent activity may take a little time to appear.</p>
<?php endif ?>
<nav class="billing-history-pagination" aria-label="Billing history date ranges">
<?php if ($history['older']!==null): ?><a data-history-link href="<?= esc($historyUrl((int)$history['selected']['id'],$history['older']),'attr') ?>">← Older payments</a><?php endif ?>
<?php if ($history['newer']!==null): ?><a data-history-link href="<?= esc($historyUrl((int)$history['selected']['id'],$history['newer']),'attr') ?>">Newer payments →</a><?php endif ?>
<a data-history-link href="<?= esc($historyUrl((int)$history['selected']['id']),'attr') ?>">Latest payments</a>
</nav>
<?php endif ?>
<?php endif ?>
