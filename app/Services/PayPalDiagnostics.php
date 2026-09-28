<?php
namespace App\Services;

/** Read-only diagnostics; never claim a payer, cancel billing, or grant access. */
class PayPalDiagnostics
{
    public function inspect(int $userId): array
    {
        $config=config('PayPal');
        $db=db_connect();
        $row=(new PayPalBilling())->latest($userId);
        $report=[
            'diagnostic_version'=>'2026-09-23.2',
            'billing_code_sha256'=>hash_file('sha256',(new \ReflectionClass(PayPalBilling::class))->getFileName()),
            'environment'=>$config->environment,
            'checked_at_utc'=>gmdate('Y-m-d H:i:s'),
            'local_status'=>$row['status']??'No agreement',
            'trial_requested'=>(bool)($row['trial_requested']??false),
            'saved_trial_end_utc'=>$row['trial_ends_at']??null,
            'last_verified_event_utc'=>$row['paypal_event_at']??null,
            'checks'=>[],
        ];
        if (!$row || !$row['provider_subscription_id']) {
            $report['summary']='There is no saved PayPal agreement to inspect in this billing environment.';
            return $report;
        }
        try {
            $details=service('paypalClient')->request('GET','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']).'?fields=plan');
        } catch (\Throwable) {
            $report['summary']='The server could not read this agreement from PayPal. Check the configured environment, credentials, and API connectivity. No billing changes were made.';
            return $report;
        }
        $billing=$details['billing_info']??[];
        $now=gmdate('Y-m-d H:i:s');
        $start=PayPalBilling::date($details['start_time']??'');
        $end=PayPalBilling::date($billing['next_billing_time']??'');
        $remote=in_array($details['status']??'', ['APPROVAL_PENDING','APPROVED','ACTIVE','SUSPENDED','CANCELLED','EXPIRED'],true)?$details['status']:'UNKNOWN';
        $savedEnd=$row['trial_ends_at']??null;
        $savedTrial=$savedEnd && $savedEnd>$now;
        $owns=($details['id']??'')===$row['provider_subscription_id']
            && ($details['plan_id']??'')===$row['provider_price_id']
            && hash_equals((string)$row['paypal_reference'],(string)($details['custom_id']??''));
        $paid=$billing['last_payment']??[];
        $paidAt=PayPalBilling::date($paid['time']??'');
        $zeroPayment=PayPalBilling::cents($paid['amount']['value']??'')===0;
        $cycleFound=false; $cycles=[];
        foreach ($billing['cycle_executions']??[] as $cycle) {
            $type=in_array($cycle['tenure_type']??'', ['TRIAL','REGULAR'],true)?$cycle['tenure_type']:'UNKNOWN';
            $cycles[]=['type'=>$type,'sequence'=>(int)($cycle['sequence']??0),
                'total_cycles'=>isset($cycle['total_cycles'])?(int)$cycle['total_cycles']:null,
                'cycles_completed'=>isset($cycle['cycles_completed'])?(int)$cycle['cycles_completed']:null];
            if ($type==='TRIAL' && (int)($cycle['sequence']??0)===1 && (int)($cycle['total_cycles']??1)===1) { $cycleFound=true; }
        }
        $payer=(string)($details['subscriber']['payer_id']??'');
        $payerAvailable=false;
        if ($payer!=='' && strlen($config->trialHashKey)>=32) {
            $claim=$db->table('trial_claims')->where(['environment'=>$config->environment,'kind'=>'payer',
                'signal_hash'=>(new TrialEligibility())->hash('payer',$payer)])->get()->getRowArray();
            $payerAvailable=!$claim || (int)$claim['subscription_id']===(int)$row['id'];
        }
        $customPrice=true;
        if (($row['discount_amount']??null)===null && $db->tableExists('member_prices')
            && $db->table('member_prices')->where('subscription_id',$row['id'])->countAllResults()) {
            $customPrice=false;
            foreach ($details['plan']['billing_cycles']??[] as $cycle) {
                $price=$cycle['pricing_scheme']['fixed_price']??[];
                if ((int)($cycle['sequence']??0)===($row['trial_requested']?2:1)
                    && ($price['currency_code']??'')===$row['billing_currency']
                    && PayPalBilling::cents($price['value']??'')===(int)$row['billing_amount']) { $customPrice=true; }
            }
        }
        $report+=['remote_status'=>$remote,'start_time_utc'=>$start,'next_billing_time_utc'=>$end,
            'expected_14_day_date'=>$start?gmdate('Y-m-d',strtotime($start.' UTC')+14*86400):null,
            'exact_14_day_seconds_match'=>(bool)($start && $end && strtotime($end.' UTC')===strtotime($start.' UTC')+14*86400),
            'provider_trial_schedule_accepted'=>PayPalTrialSchedule::matches($start,$end),
            'cycle_executions'=>$cycles];
        $checks=[
            ['Saved agreement includes a trial',(bool)$row['trial_requested'],'This checkout was saved without a trial. An approval redirect cannot turn a paid agreement into a free trial.'],
            ['Agreement matches this member',$owns,'PayPal returned a different agreement, plan, or account reference.'],
            ['PayPal agreement is active',$remote==='ACTIVE' || ($savedTrial && $remote==='CANCELLED'),'PayPal has not returned ACTIVE status. Entering payment details does not by itself establish an active agreement.'],
            ['No payment hold',empty($row['paypal_hold_at']),'A failed/refunded/reversed payment event placed this agreement on hold.'],
            ['No failed payments',(int)($billing['failed_payments_count']??0)===0,'PayPal reports a failed payment.'],
            ['No outstanding balance',PayPalBilling::cents($billing['outstanding_balance']['value']??'0')===0,'PayPal reports an outstanding or unreadable balance.'],
            ['Free-trial payment state',!$paidAt || $zeroPayment,'PayPal reports a nonzero prior payment. Paid-period validation applies instead of first-trial activation.'],
            ['Agreed custom price confirmed',$customPrice,'The agreed custom price is missing or different in PayPal’s subscription plan details.'],
            ['Trial start has arrived',(bool)($savedTrial || ($start && $start<=$now)),'PayPal’s start time is missing or still in the future.'],
            ['Trial end is in the future',(bool)($savedTrial || ($end && $end>$now)),'PayPal’s next billing time is missing or not in the future.'],
            ['Provider trial schedule is within bounds',(bool)($savedTrial || PayPalTrialSchedule::matches($start,$end)),'The provider schedule is missing or differs from the validated 14-day trial by at least 24 hours.'],
            ['Single trial cycle confirmed',(bool)($savedTrial || $cycleFound),'The provider response does not include trial sequence 1 with a single cycle.'],
            ['Payer ID is present',(bool)($savedTrial || $payer!==''),'PayPal has not returned a payer ID.'],
            ['Trial protection key is configured',(bool)($savedTrial || strlen($config->trialHashKey)>=32),'The trial hash key is missing or too short. Do not replace an existing key: that would break prior trial matching.'],
            ['Payer has not claimed a different trial',(bool)($savedTrial || $payerAvailable),'The payer is missing, cannot be checked, or has already claimed a different trial.'],
        ];
        foreach ($checks as [$label,$passed,$reason]) {
            $report['checks'][]=['check'=>$label,'passed'=>$passed,'detail'=>$passed?'Passed':$reason];
        }
        $failed=array_filter($report['checks'],static fn($check)=>!$check['passed']);
        $report['summary']=$failed
            ? 'The checks below explain which requirements for free-trial access are not currently met. This inspection did not change the subscription.'
            : 'All inspected trial checks pass. If the saved status is still incomplete, use Check PayPal status to synchronize it. If it stays incomplete, verify the deployed billing code and clear PHP OPcache; this report alone does not grant access.';
        return $report;
    }
}
