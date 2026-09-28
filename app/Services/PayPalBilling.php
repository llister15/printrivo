<?php
namespace App\Services;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

class PayPalBilling
{
    private $db;
    private \Config\PayPal $config;
    private PayPalClient $client;
    public function __construct(?PayPalClient $client = null)
    {
        $this->db = db_connect();
        $this->config = config('PayPal');
        $this->client = $client ?? service('paypalClient');
    }
    public function latest(int $userId): ?array
    {
        return $this->db->table('subscriptions')->where('user_id',$userId)->where('provider','paypal')
            ->where('provider_environment',$this->config->environment)->orderBy('id','DESC')->get()->getRowArray();
    }
    public function alphaEligible(User $user): bool
    {
        return $user->promo_code === 'ALPHA' && !$user->alpha_redeemed_at;
    }
    public function offer(User $user): array
    {
        $pending=$this->db->table('paypal_checkouts')->where(['user_id'=>$user->id,'environment'=>$this->config->environment])->get()->getRowArray();
        $row=$pending?$this->db->table('subscriptions')->where('id',$pending['subscription_id'])->get()->getRowArray():null;
        if ($row && $row['status']==='incomplete') {
            return ['amount'=>(int)$row['billing_amount'],'trial'=>(bool)$row['trial_requested'],
                'custom'=>(bool)$this->db->table('member_prices')->where('subscription_id',$row['id'])->countAllResults()];
        }
        $price=(new MemberPrice())->available((int)$user->id);
        return ['amount'=>$price?(int)$price['amount']:($this->alphaEligible($user)?499:999),
            'trial'=>(new TrialEligibility())->eligible($user),'custom'=>(bool)$price];
    }
    public function checkout(User $user, ?bool $expectedTrial = null, ?int $expectedAmount = null): string
    {
        if (!$this->config->ready()) { throw new RuntimeException('PayPal configuration is incomplete.'); }
        if ((new SubscriptionAccess())->activeFor((int)$user->id)) { throw new RuntimeException('You already have an active subscription.'); }
        $pending = $this->db->table('paypal_checkouts')->where('user_id',$user->id)->where('environment',$this->config->environment)->get()->getRowArray();
        if ($pending) {
            $row = $this->db->table('subscriptions')->where('id',$pending['subscription_id'])->get()->getRowArray();
            if ($row['status']==='incomplete' && (($expectedAmount!==null && $expectedAmount!==(int)$row['billing_amount'])
                || ($expectedTrial!==null && $expectedTrial!==(bool)$row['trial_requested']))) {
                throw new RuntimeException('Review the saved checkout terms.');
            }
            if ($row['provider_subscription_id']) {
                $this->refresh((int)$user->id);
                $row = $this->db->table('subscriptions')->where('id',$row['id'])->get()->getRowArray();
                if (in_array($row['status'],['active','trialing','past_due','suspended','unpaid'],true)) {
                    throw new RuntimeException('A PayPal subscription already exists. Manage it before starting another.');
                }
                if (in_array($row['status'],['canceled','expired'],true)) {
                    $this->db->table('paypal_checkouts')->where('id',$pending['id'])->delete();
                    $pending = null;
                } elseif ($pending['approval_url']) { return $this->approvalUrl($pending['approval_url']); }
                else { throw new RuntimeException('Your subscription is awaiting payment confirmation. Please check its status shortly.'); }
            }
        }
        if (!$pending) {
            // Check current database state, never a client-supplied price or discount.
            $fresh = model(\App\Models\UserModel::class)->findById($user->id);
            $price=(new MemberPrice())->available((int)$user->id);
            $alpha = !$price && $this->alphaEligible($fresh);
            $trial = (new TrialEligibility())->eligible($fresh);
            if ($expectedTrial !== null && $expectedTrial !== $trial) {
                throw new RuntimeException('Trial eligibility changed. Review billing before continuing.');
            }
            $planId = $alpha ? $this->config->alphaPlanId : $this->config->standardPlanId;
            if ($trial) { $planId = $alpha ? $this->config->alphaTrialPlanId : $this->config->standardTrialPlanId; }
            $amount = $price?(int)$price['amount']:($alpha ? 499 : 999);
            if ($expectedAmount!==null && $expectedAmount!==$amount) { throw new RuntimeException('The agreed price changed. Review it before continuing.'); }
            $this->validatePlan($this->client->request('GET','/v1/billing/plans/'.rawurlencode($planId)), $alpha?499:999, $trial);
            $reference = bin2hex(random_bytes(16));
            $body = ['plan_id'=>$planId,'custom_id'=>$reference,'quantity'=>'1','application_context'=>[
                'brand_name'=>'Printrivo','shipping_preference'=>'NO_SHIPPING','user_action'=>'SUBSCRIBE_NOW',
                'return_url'=>site_url('billing/paypal/return'),'cancel_url'=>site_url('account/subscription'),
            ]];
            if ($price) {
                $body['plan']=['billing_cycles'=>[['sequence'=>$trial?2:1,
                    'pricing_scheme'=>['fixed_price'=>['value'=>number_format($amount/100,2,'.',''),'currency_code'=>'USD']]]]];
            }
            $this->db->transBegin();
            try {
                $this->db->table('subscriptions')->insert([
                    'user_id'=>$user->id,'provider'=>'paypal','provider_environment'=>$this->config->environment,
                    'provider_price_id'=>$planId,'paypal_reference'=>$reference,'billing_amount'=>$amount,
                    'billing_currency'=>$this->config->currency,'trial_requested'=>$trial,'billing_consent_at'=>gmdate('Y-m-d H:i:s'),'status'=>'incomplete','created_at'=>gmdate('Y-m-d H:i:s'),
                ]);
                $localId=$this->db->insertID();
                if ($price) {
                    $this->db->table('member_prices')->where('id',$price['id'])->where('subscription_id',null)
                        ->where('amount',$amount)->update(['subscription_id'=>$localId]);
                    if ($this->db->affectedRows()!==1) { throw new RuntimeException('The agreed price changed.'); }
                }
                if ($trial) { (new TrialEligibility())->reserve($fresh,(int)$localId); }
                $pending=['user_id'=>$user->id,'environment'=>$this->config->environment,'subscription_id'=>$localId,
                    'request_id'=>$reference,'request_body'=>json_encode($body,JSON_THROW_ON_ERROR),'created_at'=>gmdate('Y-m-d H:i:s')];
                $this->db->table('paypal_checkouts')->insert($pending);
                if (!$this->db->transStatus()) { throw new RuntimeException('Checkout is already being prepared. Please try again.'); }
                $this->db->transCommit();
            } catch (\Throwable $e) { $this->db->transRollback(); throw new RuntimeException('Checkout is already being prepared. Please try again.'); }
        }
        // Retry the identical request after timeouts. Never blindly create another agreement.
        if (strtotime($pending['created_at'].' UTC') < time()-23*3600) {
            throw new RuntimeException('This checkout needs a support review before retrying. No new subscription has been created.');
        }
        $result=$this->client->request('POST','/v1/billing/subscriptions',json_decode($pending['request_body'],true),$pending['request_id']);
        $id=$result['id']??'';
        if (!preg_match('/^I-[A-Z0-9]+$/',$id)) { throw new RuntimeException('PayPal did not return a subscription reference.'); }
        $approve=null;
        foreach ($result['links']??[] as $link) { if (($link['rel']??'')==='approve') { $approve=$this->approvalUrl($link['href']); } }
        $this->db->table('subscriptions')->where('id',$pending['subscription_id'])->update(['provider_subscription_id'=>$id]);
        $this->db->table('paypal_checkouts')->where('user_id',$user->id)->where('environment',$this->config->environment)->update(['approval_url'=>$approve]);
        if (!$approve) { throw new RuntimeException('Your subscription is awaiting confirmation. Use Check payment status shortly.'); }
        return $approve;
    }
    private function approvalUrl(string $url): string
    {
        $expected=$this->config->environment==='live'?'www.paypal.com':'www.sandbox.paypal.com';
        if (parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!==$expected||parse_url($url,PHP_URL_USER)||parse_url($url,PHP_URL_PORT)) {
            throw new RuntimeException('PayPal returned an invalid approval link.');
        }
        return $url;
    }
    public function validatePlan(array $plan, int $amount, bool $trial = false): void
    {
        $cycles=$plan['billing_cycles']??[];
        if ($trial) {
            $intro = array_shift($cycles) ?? [];
            if (($intro['tenure_type']??'') !== 'TRIAL' || (int)($intro['sequence']??0)!==1
                || ($intro['frequency']['interval_unit']??'')!=='DAY' || (int)($intro['frequency']['interval_count']??0)!==14
                || (int)($intro['total_cycles']??0)!==1
                || self::cents($intro['pricing_scheme']['fixed_price']['value']??'0')!==0) {
                throw new RuntimeException('The PayPal plan must include exactly 14 free days.');
            }
        }
        $cycle=$cycles[0]??[];
        if ($trial && (int)($cycle['sequence']??0)!==2) { throw new RuntimeException('Invalid trial billing sequence.'); }
        $price=$cycle['pricing_scheme']['fixed_price']??[];
        if (($plan['status']??'')!=='ACTIVE'||count($cycles)!==1||($cycle['tenure_type']??'')!=='REGULAR'
            ||($cycle['frequency']['interval_unit']??'')!=='MONTH'||(int)($cycle['frequency']['interval_count']??0)!==1
            ||(int)($cycle['total_cycles']??-1)!==0||($price['currency_code']??'')!==$this->config->currency
            ||self::cents($price['value']??'')!==$amount||self::cents($plan['payment_preferences']['setup_fee']['value']??'0')!==0
            ||(float)($plan['taxes']['percentage']??0)!==0.0) {
            throw new RuntimeException('The PayPal plan does not match the advertised monthly price. Please contact support.');
        }
    }
    public static function cents($value): int
    {
        if (!is_string($value)||!preg_match('/^\d+(?:\.\d{1,2})?$/',$value)) { return -1; }
        return (int)round((float)$value*100);
    }
    public function refresh(int $userId): void
    {
        $row=$this->latest($userId);
        if (!$row || !$row['provider_subscription_id']) { return; }
        $details=$this->client->request('GET','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']).'?fields=plan');
        $this->db->transBegin();
        try { $this->apply($row,$details); $this->db->transCommit(); }
        catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
    }
    public function cancel(int $userId, ?int $expectedSubscriptionId = null): void
    {
        $row=$this->latest($userId);
        if (!$row || !$row['provider_subscription_id']) { throw new RuntimeException('No PayPal subscription was found.'); }
        if ($expectedSubscriptionId !== null && (int)$row['id'] !== $expectedSubscriptionId) { throw new RuntimeException('Subscription changed; review it before canceling.'); }
        $details=$this->client->request('GET','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']).'?fields=plan');
        $this->assertOwnership($row,$details);
        if (!in_array($details['status']??'', ['CANCELLED','EXPIRED'],true)) {
            $this->client->request('POST','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']).'/cancel',['reason'=>'Customer canceled in Printrivo.']);
        }
        $this->refresh($userId);
    }
    private function assertOwnership(array $row,array $details): void
    {
        if (($details['id']??'')!==$row['provider_subscription_id']||($details['plan_id']??'')!==$row['provider_price_id']
            ||!hash_equals((string)$row['paypal_reference'],(string)($details['custom_id']??''))) {
            throw new RuntimeException('PayPal subscription details did not match this account.');
        }
    }
    public function discount(int $adminId,int $userId,int $expectedId,int $amount): void
    {
        AdminMembers::assertAdmin($adminId);
        $row=$this->latest($userId);
        if (!$row || (int)$row['id']!==$expectedId || !$row['provider_subscription_id'] || $row['cancel_at_period_end']
            || !in_array($row['status'],['active','trialing'],true) || $amount<1 || $amount>=(int)$row['billing_amount']) {
            throw new RuntimeException('Choose a lower positive amount for the current active agreement.');
        }
        if ($row['discount_amount']!==null && !$row['discount_confirmed_at']) { throw new RuntimeException('A discount is awaiting confirmation. Check PayPal status first.'); }
        $details=$this->client->request('GET','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']).'?fields=plan');
        $this->assertOwnership($row,$details);
        if (($details['status']??'')!=='ACTIVE') { throw new RuntimeException('PayPal agreement is not active.'); }
        if ($row['discount_amount']!==null && self::cents($details['billing_info']['last_payment']['amount']['value']??'')!==(int)$row['billing_amount']) {
            throw new RuntimeException('Wait until the previous discount has appeared on a payment before changing it again.');
        }
        $now=gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            // Reserve one change before the external request. An uncertain PATCH is
            // reconciled from PayPal; it must not become an untracked billing change.
            $this->db->table('subscriptions')->where('id',$row['id'])->where('billing_amount',$row['billing_amount'])
                ->where('discount_amount',$row['discount_amount'])->where('discount_confirmed_at',$row['discount_confirmed_at'])
                ->where('discount_requested_at',$row['discount_requested_at'])->update([
                    'discount_amount'=>$amount,'previous_billing_amount'=>$row['billing_amount'],
                    'discount_requested_at'=>$now,'discount_confirmed_at'=>null,'discount_old_price_until'=>null,'discount_admin_id'=>$adminId,
                ]);
            if ($this->db->affectedRows()!==1) { throw new RuntimeException('Another price change is in progress.'); }
            $this->db->table('admin_actions')->insert(['admin_id'=>$adminId,'user_id'=>$userId,'subscription_id'=>$row['id'],
                'action'=>'discount_'.$row['billing_amount'].'_to_'.$amount,'outcome'=>'requested','created_at'=>$now]);
            if (!$this->db->transStatus()) { throw new RuntimeException('Discount could not be saved.'); }
            $this->db->transCommit();
        } catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
        $this->submitDiscount($row, $amount);
    }
    private function submitDiscount(array $row,int $amount): void
    {
        $sequence=$row['trial_requested']?2:1;
        $this->client->request('PATCH','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']),[
            ['op'=>'replace','path'=>'/plan/billing_cycles/@sequence=='.$sequence.'/pricing_scheme/fixed_price',
                'value'=>['currency_code'=>'USD','value'=>number_format($amount/100,2,'.','')]],
        ]);
        $this->refresh((int)$row['user_id']);
    }
    public function retryDiscount(int $adminId,int $userId,int $expectedId): void
    {
        AdminMembers::assertAdmin($adminId);
        $this->refresh($userId);
        $row=$this->latest($userId);
        if (!$row || (int)$row['id']!==$expectedId || $row['discount_amount']===null) { throw new RuntimeException('No matching discount request.'); }
        if ($row['discount_confirmed_at']) { return; }
        if (!in_array($row['status'],['active','trialing'],true) || $row['cancel_at_period_end']) { throw new RuntimeException('Agreement is not active.'); }
        // Replacing the same price is safe after a timeout. Never create a new agreement.
        $this->submitDiscount($row,(int)$row['discount_amount']);
    }
    private function confirmDiscount(array $row,array $details): array
    {
        if ($row['discount_amount']===null || $row['discount_confirmed_at']) { return $row; }
        foreach ($details['plan']['billing_cycles']??[] as $cycle) {
            $price=$cycle['pricing_scheme']['fixed_price']??[];
            if ((int)($cycle['sequence']??0)!==($row['trial_requested']?2:1)
                || ($price['currency_code']??'')!==$row['billing_currency']
                || self::cents($price['value']??'')!==(int)$row['discount_amount']) { continue; }
            $now=gmdate('Y-m-d H:i:s');
            $update=['billing_amount'=>$row['discount_amount'],'discount_confirmed_at'=>$now,
                'discount_old_price_until'=>gmdate('Y-m-d H:i:s',time()+10*86400)];
            $this->db->table('subscriptions')->where('id',$row['id'])->where('discount_confirmed_at',null)
                ->where('discount_amount',$row['discount_amount'])->where('discount_requested_at',$row['discount_requested_at'])->update($update);
            if ($this->db->affectedRows()!==1) {
                return $this->db->table('subscriptions')->where('id',$row['id'])->get()->getRowArray();
            }
            $this->db->table('admin_actions')->where('subscription_id',$row['id'])
                ->where('action','discount_'.$row['previous_billing_amount'].'_to_'.$row['discount_amount'])->where('outcome','requested')
                ->update(['outcome'=>'confirmed']);
            return array_replace($row,$update);
        }
        return $row;
    }
    private function apply(array $row,array $details,?array $event=null): void
    {
        $this->assertOwnership($row,$details);
        $previousEventAt=$row['paypal_event_at'];
        $row=$this->db->table('subscriptions')->where('id',$row['id'])->get()->getRowArray();
        // A webhook received during the API request takes precedence over this refresh.
        if (!$event && $previousEventAt!==$row['paypal_event_at']) { return; }
        $eventAt=$event?self::date($event['create_time']??''):null;
        if ($event && !$eventAt) { throw new RuntimeException('Invalid PayPal event timestamp.'); }
        if ($eventAt && $row['paypal_event_at'] && $eventAt<$row['paypal_event_at']) { return; }
        $row=$this->confirmDiscount($row,$details);
        $customPriceConfirmed=true;
        if ($row['discount_amount']===null && $this->db->table('member_prices')->where('subscription_id',$row['id'])->countAllResults()) {
            $customPriceConfirmed=false;
            foreach ($details['plan']['billing_cycles']??[] as $cycle) {
                $price=$cycle['pricing_scheme']['fixed_price']??[];
                if ((int)($cycle['sequence']??0)===($row['trial_requested']?2:1)
                    && ($price['currency_code']??'')===$row['billing_currency']
                    && self::cents($price['value']??'')===(int)$row['billing_amount']) { $customPriceConfirmed=true; }
            }
        }
        $hold=$row['paypal_hold_at'];
        $type=$event['event_type']??'';
        if (in_array($type,['BILLING.SUBSCRIPTION.PAYMENT.FAILED','PAYMENT.SALE.REFUNDED','PAYMENT.SALE.REVERSED'],true)) { $hold=$eventAt; }
        $billing=$details['billing_info']??[];
        $paid=$billing['last_payment']??[];
        $paidAt=self::date($paid['time']??'');
        // A zero-value trial entry is not a paid renewal.
        if ($row['trial_requested'] && self::cents($paid['amount']['value']??'')===0) { $paidAt=null; }
        $end=self::date($billing['next_billing_time']??'');
        $remote=$details['status']??'';
        if ($remote==='CANCELLED' && !$end) { $end=$row['current_period_end']; }
        $paymentAmount=self::cents($paid['amount']['value']??'');
        // Preserve the already-paid month and PayPal's 10-day price-change notice
        // window. Old-price payments cannot authorize renewals indefinitely.
        $amountMatches=$paymentAmount===(int)$row['billing_amount']
            || ($row['discount_confirmed_at'] && $paidAt && $paidAt<=$row['discount_old_price_until']
                && $paymentAmount===(int)$row['previous_billing_amount']);
        $validPayment=$paidAt && (!$hold||$paidAt>$hold)
            && $amountMatches
            && ($paid['amount']['currency_code']??'')===$row['billing_currency']
            && (int)($billing['failed_payments_count']??0)===0
            && self::cents($billing['outstanding_balance']['value']??'0')===0
            && $end && $end>gmdate('Y-m-d H:i:s') && $end>$paidAt
            && strtotime($end.' UTC')<=strtotime($paidAt.' UTC')+35*86400;
        $status=match($remote){'CANCELLED'=>'canceled','EXPIRED'=>'expired','SUSPENDED'=>'suspended','ACTIVE'=>'incomplete',default=>'incomplete'};
        if ($remote==='ACTIVE' && $hold && (!$paidAt||$paidAt<=$hold)) { $status='unpaid'; }
        if ($remote==='ACTIVE' && ((int)($billing['failed_payments_count']??0)>0 || self::cents($billing['outstanding_balance']['value']??'0')>0)) { $status='past_due'; }
        if (in_array($remote,['ACTIVE','CANCELLED'],true)&&$validPayment) { $status='active'; }
        // Free access requires a validated trial plan, confirmed ACTIVE agreement and
        // the provider's bounded trial schedule. A redirect or local signup is insufficient.
        if ($row['trial_requested'] && $customPriceConfirmed && !$paidAt && !$hold && in_array($remote,['ACTIVE','CANCELLED'],true)
            && (int)($billing['failed_payments_count']??0)===0
            && self::cents($billing['outstanding_balance']['value']??'0')===0) {
            $start=self::date($details['start_time']??'');
            $trialEnd=$row['trial_ends_at'];
            if (!$trialEnd && $remote==='ACTIVE' && $start && $end
                && $start<=gmdate('Y-m-d H:i:s') && $end>gmdate('Y-m-d H:i:s')
                // The validated DAY/14 plan establishes trial duration. The provider's
                // billing calendar may cross a different UTC date than start + 14 days.
                && PayPalTrialSchedule::matches($start,$end)) {
                foreach ($billing['cycle_executions']??[] as $cycle) {
                    if (($cycle['tenure_type']??'')==='TRIAL' && (int)($cycle['sequence']??0)===1
                        // total_cycles is optional in PayPal's execution response;
                        // the single trial cycle was validated on the plan at checkout.
                        && (int)($cycle['total_cycles']??1)===1) {
                        $payer=(string)($details['subscriber']['payer_id']??'');
                        if ($payer !== '' && !(new TrialEligibility())->claimPayer($payer,(int)$row['id'])) {
                            // Cancel a repeat free-trial agreement before it can bill. Never silently charge instead.
                            $this->client->request('POST','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']).'/cancel',
                                ['reason'=>'Introductory trial already used. Please return to Printrivo to review a paid subscription.']);
                            $remote='CANCELLED'; $status='canceled';
                        } elseif ($payer !== '') { $trialEnd=$end; }
                    }
                }
            }
            if ($trialEnd && $trialEnd>gmdate('Y-m-d H:i:s')) { $status='trialing'; }
        }
        $update=['status'=>$status,'cancel_at_period_end'=>$remote==='CANCELLED',
            'provider_customer_id'=>$details['subscriber']['payer_id']??null,
            'paypal_hold_at'=>$hold,'updated_at'=>gmdate('Y-m-d H:i:s')];
        if ($status==='trialing') { $update['trial_ends_at']=$trialEnd; $update['current_period_end']=$trialEnd; }
        if ($validPayment) { $update['current_period_start']=$paidAt; $update['current_period_end']=$end; }
        if ($eventAt) { $update['paypal_event_at']=$eventAt; }
        $this->db->table('subscriptions')->where('id',$row['id'])->update($update);
        if ($status==='active' && (int)$row['billing_amount']===499) {
            $this->db->table('users')->where('id',$row['user_id'])->where('alpha_redeemed_at',null)->update(['alpha_redeemed_at'=>gmdate('Y-m-d H:i:s')]);
        }
        if (!$this->db->transStatus()) { throw new RuntimeException('Payment status could not be saved.'); }
    }
    public static function date(string $value): ?string
    {
        $time=$value!==''?strtotime($value):false;
        return $time===false?null:gmdate('Y-m-d H:i:s',$time);
    }
    public function webhook(array $headers,array $event): void
    {
        if (!$this->client->verify($headers,$event)) { throw new \UnexpectedValueException('Invalid PayPal signature.'); }
        $eventId=$event['id']??'';
        $type=$event['event_type']??'';
        if (!is_string($eventId)||strlen($eventId)>128||$eventId===''||!is_string($type)||strlen($type)>80) { throw new \UnexpectedValueException('Invalid PayPal event.'); }
        $key=['provider'=>'paypal','environment'=>$this->config->environment,'event_id'=>$eventId];
        if ($this->db->table('payment_events')->where($key)->countAllResults()) { return; }
        $supported=['BILLING.SUBSCRIPTION.CREATED','BILLING.SUBSCRIPTION.ACTIVATED','BILLING.SUBSCRIPTION.UPDATED',
            'BILLING.SUBSCRIPTION.CANCELLED','BILLING.SUBSCRIPTION.SUSPENDED','BILLING.SUBSCRIPTION.EXPIRED',
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED','PAYMENT.SALE.COMPLETED','PAYMENT.SALE.REFUNDED','PAYMENT.SALE.REVERSED'];
        if (!in_array($type,$supported,true)) { return; }
        $resource=$event['resource']??[];
        $id=str_starts_with($type,'BILLING.SUBSCRIPTION.')?($resource['id']??''):($resource['billing_agreement_id']??'');
        if (!$id && !empty($resource['sale_id'])) {
            $sale=$this->client->request('GET','/v1/payments/sale/'.rawurlencode($resource['sale_id']));
            $id=$sale['billing_agreement_id']??'';
        }
        if (!$id || !preg_match('/^I-[A-Z0-9]+$/',$id)) { return; }
        $details=$this->client->request('GET','/v1/billing/subscriptions/'.rawurlencode($id).'?fields=plan');
        $row=$this->db->table('subscriptions')->where('provider','paypal')->where('provider_environment',$this->config->environment)
            ->where('paypal_reference',$details['custom_id']??'')->get()->getRowArray();
        if (!$row) { return; } // Other applications may share the merchant account.
        if ($row['provider_subscription_id'] && $row['provider_subscription_id']!==$id) { throw new \UnexpectedValueException('Subscription reference mismatch.'); }
        $row['provider_subscription_id']=$id;
        $this->db->transBegin();
        try {
            $this->db->table('payment_events')->insert($key+['event_type'=>$type,'processed_at'=>gmdate('Y-m-d H:i:s')]);
            $this->db->table('subscriptions')->where('id',$row['id'])->update(['provider_subscription_id'=>$id]);
            $this->apply($row,$details,$event);
            if (!$this->db->transStatus()) { throw new RuntimeException('Webhook processing failed.'); }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            if ($this->db->table('payment_events')->where($key)->countAllResults()) { return; }
            throw $e;
        }
    }
}
