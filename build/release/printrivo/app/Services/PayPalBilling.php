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
    public function checkout(User $user, ?bool $expectedTrial = null): string
    {
        if (!$this->config->ready()) { throw new RuntimeException('PayPal configuration is incomplete.'); }
        if ((new SubscriptionAccess())->activeFor((int)$user->id)) { throw new RuntimeException('You already have an active subscription.'); }
        $pending = $this->db->table('paypal_checkouts')->where('user_id',$user->id)->where('environment',$this->config->environment)->get()->getRowArray();
        if ($pending) {
            $row = $this->db->table('subscriptions')->where('id',$pending['subscription_id'])->get()->getRowArray();
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
            $alpha = $this->alphaEligible($fresh);
            $trial = (new TrialEligibility())->eligible($fresh);
            if ($expectedTrial !== null && $expectedTrial !== $trial) {
                throw new RuntimeException('Trial eligibility changed. Review billing before continuing.');
            }
            $planId = $alpha ? $this->config->alphaPlanId : $this->config->standardPlanId;
            if ($trial) { $planId = $alpha ? $this->config->alphaTrialPlanId : $this->config->standardTrialPlanId; }
            $amount = $alpha ? 499 : 999;
            $this->validatePlan($this->client->request('GET','/v1/billing/plans/'.rawurlencode($planId)), $amount, $trial);
            $reference = bin2hex(random_bytes(16));
            $body = ['plan_id'=>$planId,'custom_id'=>$reference,'quantity'=>'1','application_context'=>[
                'brand_name'=>'Printrivo','shipping_preference'=>'NO_SHIPPING','user_action'=>'SUBSCRIBE_NOW',
                'return_url'=>site_url('billing/paypal/return'),'cancel_url'=>site_url('account/subscription'),
            ]];
            $this->db->transBegin();
            try {
                $this->db->table('subscriptions')->insert([
                    'user_id'=>$user->id,'provider'=>'paypal','provider_environment'=>$this->config->environment,
                    'provider_price_id'=>$planId,'paypal_reference'=>$reference,'billing_amount'=>$amount,
                    'billing_currency'=>$this->config->currency,'trial_requested'=>$trial,'billing_consent_at'=>gmdate('Y-m-d H:i:s'),'status'=>'incomplete','created_at'=>gmdate('Y-m-d H:i:s'),
                ]);
                $localId=$this->db->insertID();
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
        $details=$this->client->request('GET','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']));
        $this->db->transBegin();
        try { $this->apply($row,$details); $this->db->transCommit(); }
        catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
    }
    public function cancel(int $userId, ?int $expectedSubscriptionId = null): void
    {
        $row=$this->latest($userId);
        if (!$row || !$row['provider_subscription_id']) { throw new RuntimeException('No PayPal subscription was found.'); }
        if ($expectedSubscriptionId !== null && (int)$row['id'] !== $expectedSubscriptionId) { throw new RuntimeException('Subscription changed; review it before canceling.'); }
        $details=$this->client->request('GET','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']));
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
        $hold=$row['paypal_hold_at'];
        $type=$event['event_type']??'';
        if (in_array($type,['BILLING.SUBSCRIPTION.PAYMENT.FAILED','PAYMENT.SALE.REFUNDED','PAYMENT.SALE.REVERSED'],true)) { $hold=$eventAt; }
        $billing=$details['billing_info']??[];
        $paid=$billing['last_payment']??[];
        $paidAt=self::date($paid['time']??'');
        $end=self::date($billing['next_billing_time']??'');
        $remote=$details['status']??'';
        if ($remote==='CANCELLED' && !$end) { $end=$row['current_period_end']; }
        $validPayment=$paidAt && (!$hold||$paidAt>$hold)
            && self::cents($paid['amount']['value']??'')===(int)$row['billing_amount']
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
        if ($row['trial_requested'] && !$paidAt && !$hold && in_array($remote,['ACTIVE','CANCELLED'],true)
            && (int)($billing['failed_payments_count']??0)===0
            && self::cents($billing['outstanding_balance']['value']??'0')===0) {
            $start=self::date($details['start_time']??'');
            $trialEnd=$row['trial_ends_at'];
            if (!$trialEnd && $remote==='ACTIVE' && $start && $end
                && $start<=gmdate('Y-m-d H:i:s') && $end>gmdate('Y-m-d H:i:s')
                && strtotime($end.' UTC')===strtotime($start.' UTC')+14*86400) {
                foreach ($billing['cycle_executions']??[] as $cycle) {
                    if (($cycle['tenure_type']??'')==='TRIAL' && (int)($cycle['sequence']??0)===1
                        && (int)($cycle['total_cycles']??0)===1) {
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
        $details=$this->client->request('GET','/v1/billing/subscriptions/'.rawurlencode($id));
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
