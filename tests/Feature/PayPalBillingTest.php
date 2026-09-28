<?php
use App\Models\UserModel;
use App\Services\PayPalBilling;
use App\Services\PayPalClient;
use App\Services\SubscriptionAccess;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;

final class PayPalBillingTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticationTesting;
    protected $namespace=null;
    protected $refresh=true;
    private array $original=[];
    protected function setUp(): void
    {
        parent::setUp();
        $config=config('PayPal');
        foreach (['enabled'=>true,'environment'=>'sandbox','clientId'=>'test-client','secret'=>'test-secret','webhookId'=>'test-webhook','standardPlanId'=>'P-STANDARD','alphaPlanId'=>'P-ALPHA','standardTrialPlanId'=>'','alphaTrialPlanId'=>'','trialHashKey'=>''] as $key=>$value) {
            $this->original[$key]=$config->$key; $config->$key=$value;
        }
    }
    protected function tearDown(): void
    {
        auth()->logout();
        foreach ($this->original as $key=>$value) { config('PayPal')->$key=$value; }
        parent::tearDown();
    }
    private function member(bool $alpha=false): User
    {
        $model=model(UserModel::class);
        $user=new User(['email'=>'paypal-test@example.test','password'=>'LongExamplePassword!99','first_name'=>'PayPal','last_name'=>'Tester','active'=>1,'promo_code'=>$alpha?'ALPHA':null]);
        $model->save($user);
        return $model->findById($model->getInsertID());
    }
    public function testUnconfiguredPurchaseShowsAnErrorInsteadOfDisablingTheButton(): void
    {
        config('PayPal')->enabled = false;
        $this->actingAs($this->member());
        $page = $this->get('/account/subscription');
        $page->assertSee('Purchase subscription for $9.99/month');
        $this->assertStringNotContainsString(' disabled', $page->response()->getBody());
        $this->post('/billing/paypal/checkout', [csrf_token() => csrf_hash()])
            ->assertRedirectTo(site_url('account/subscription'));
        $this->assertStringContainsString('payment setup is incomplete', (string) session('billing_error'));
        $this->assertSame(0, $this->db->table('subscriptions')->countAllResults());
    }
    private function subscription(User $user,int $amount=999): array
    {
        $this->db->table('subscriptions')->insert(['user_id'=>$user->id,'provider'=>'paypal','provider_environment'=>'sandbox',
            'provider_subscription_id'=>'I-TEST','provider_price_id'=>$amount===499?'P-ALPHA':'P-STANDARD','paypal_reference'=>'ref-test',
            'billing_amount'=>$amount,'billing_currency'=>'USD','status'=>'incomplete']);
        return $this->db->table('subscriptions')->where('id',$this->db->insertID())->get()->getRowArray();
    }
    private function details(array $row,string $status='ACTIVE'): array
    {
        return ['id'=>$row['provider_subscription_id'],'plan_id'=>$row['provider_price_id'],'custom_id'=>$row['paypal_reference'],'status'=>$status,
            'billing_info'=>['last_payment'=>['time'=>gmdate('c',time()-3600),'amount'=>['value'=>number_format($row['billing_amount']/100,2,'.',''),'currency_code'=>'USD']],
                'next_billing_time'=>gmdate('c',time()+28*86400),'failed_payments_count'=>0,'outstanding_balance'=>['value'=>'0.00']]];
    }
    private function event(string $id='WH-1',string $type='PAYMENT.SALE.COMPLETED',int $offset=0): array
    {
        return ['id'=>$id,'event_type'=>$type,'create_time'=>gmdate('c',time()+$offset),'resource'=>['id'=>'I-TEST','billing_agreement_id'=>'I-TEST']];
    }
    private function client(array $details,bool $signature=true): PayPalClient
    {
        $client=$this->createMock(PayPalClient::class);
        $client->method('verify')->willReturn($signature);
        $client->method('request')->willReturn($details);
        return $client;
    }
    public function testActivationNeedsPaymentNotJustApproval(): void
    {
        $user=$this->member();$row=$this->subscription($user);$details=$this->details($row);
        unset($details['billing_info']['last_payment']);
        (new PayPalBilling($this->client($details)))->webhook([],$this->event('WH-APPROVED','BILLING.SUBSCRIPTION.ACTIVATED'));
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $this->actingAs($user);
        \Config\Services::injectMock('paypalClient',$this->client($details));
        $this->get('/billing/paypal/return?subscription_id=I-FORGED')->assertRedirectTo(site_url('account/subscription'));
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
    }
    public function testPaidRenewalAndIdempotentWebhook(): void
    {
        $user=$this->member();$row=$this->subscription($user);$client=$this->client($this->details($row));
        $client->expects($this->once())->method('request');
        $billing=new PayPalBilling($client);
        $billing->webhook([],$this->event());$billing->webhook([],$this->event());
        $this->assertNotNull((new SubscriptionAccess())->activeFor($user->id));
        $this->assertSame(1,$this->db->table('payment_events')->countAllResults());
        $later=$this->details($row);$later['billing_info']['last_payment']['time']=gmdate('c');$later['billing_info']['next_billing_time']=gmdate('c',time()+30*86400);
        (new PayPalBilling($this->client($later)))->webhook([],$this->event('WH-RENEW','PAYMENT.SALE.COMPLETED',1));
        $this->assertSame(gmdate('Y-m-d H:i:s',time()+30*86400),(new SubscriptionAccess())->activeFor($user->id)['current_period_end']);
        config('PayPal')->environment='live';
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
    }
    public function testInvalidSignatureAndOwnershipCannotGrantAccess(): void
    {
        $user=$this->member();$row=$this->subscription($user);
        try { (new PayPalBilling($this->client($this->details($row),false)))->webhook([],$this->event());$this->fail('Signature accepted'); }
        catch (UnexpectedValueException) {}
        $this->assertSame(0,$this->db->table('payment_events')->countAllResults());
        $details=$this->details($row);$details['plan_id']='P-OTHER';
        try { (new PayPalBilling($this->client($details)))->webhook([],$this->event());$this->fail('Mismatched plan accepted'); }
        catch (RuntimeException) {}
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $this->assertSame(0,$this->db->table('payment_events')->countAllResults());
    }
    public function testFailureRefundAndOutOfOrderEventsDoNotRestoreAccess(): void
    {
        $user=$this->member();$row=$this->subscription($user);$details=$this->details($row);
        $billing=new PayPalBilling($this->client($details));
        $billing->webhook([],$this->event('WH-PAID','PAYMENT.SALE.COMPLETED',-20));
        $this->assertNotNull((new SubscriptionAccess())->activeFor($user->id));
        $billing->webhook([],$this->event('WH-FAIL','BILLING.SUBSCRIPTION.PAYMENT.FAILED',-10));
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $billing->webhook([],$this->event('WH-OLD','PAYMENT.SALE.COMPLETED',-15));
        $billing->refresh($user->id);
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $details['billing_info']['last_payment']['time']=gmdate('c');
        $billing=new PayPalBilling($this->client($details));
        $billing->webhook([],$this->event('WH-RECOVER'));
        $this->assertNotNull((new SubscriptionAccess())->activeFor($user->id));
        $billing->webhook([],$this->event('WH-REFUND','PAYMENT.SALE.REFUNDED',1));
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
    }
    public function testCanceledKeepsOnlyPaidPeriodAndSuspendedIsBlocked(): void
    {
        $user=$this->member();$row=$this->subscription($user);$details=$this->details($row);
        (new PayPalBilling($this->client($details)))->refresh($user->id);
        $details['status']='CANCELLED';unset($details['billing_info']['next_billing_time']);
        (new PayPalBilling($this->client($details)))->refresh($user->id);
        $active=(new SubscriptionAccess())->activeFor($user->id);
        $this->assertNotNull($active);$this->assertEquals(1,$active['cancel_at_period_end']);
        $this->db->table('subscriptions')->where('id',$row['id'])->update(['current_period_end'=>gmdate('Y-m-d H:i:s',time()-1)]);
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $details=$this->details($row,'SUSPENDED');
        (new PayPalBilling($this->client($details)))->refresh($user->id);
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
    }
    public function testWrongAmountCurrencyOrPastDueDoesNotGrantAccess(): void
    {
        $user=$this->member();$row=$this->subscription($user);
        foreach (['amount','currency','failed','expired'] as $case) {
            $details=$this->details($row);
            if ($case==='amount')$details['billing_info']['last_payment']['amount']['value']='0.01';
            if ($case==='currency')$details['billing_info']['last_payment']['amount']['currency_code']='EUR';
            if ($case==='failed')$details['billing_info']['failed_payments_count']=1;
            if ($case==='expired')$details['billing_info']['next_billing_time']=gmdate('c',time()-10);
            (new PayPalBilling($this->client($details)))->refresh($user->id);
            $this->assertNull((new SubscriptionAccess())->activeFor($user->id),$case);
        }
    }
    public function testAlphaIsRedeemedOnlyAfterPayment(): void
    {
        $user=$this->member(true);$row=$this->subscription($user,499);$billing=new PayPalBilling($this->client($this->details($row)));
        $this->assertTrue($billing->alphaEligible($user));
        $billing->refresh($user->id);
        $user=model(UserModel::class)->findById($user->id);
        $this->assertNotNull($user->alpha_redeemed_at);
        $this->assertFalse($billing->alphaEligible($user));
        $this->assertNotNull((new SubscriptionAccess())->activeFor($user->id));
    }
    public function testMonthlyPlanRejectsTrialOrIncorrectPrice(): void
    {
        $billing=new PayPalBilling($this->client([]));
        $plan=$this->plan(999);$billing->validatePlan($plan,999);
        $plan['billing_cycles'][0]['tenure_type']='TRIAL';
        $this->expectException(RuntimeException::class);$billing->validatePlan($plan,999);
    }
    private function plan(int $amount): array
    {
        return ['status'=>'ACTIVE','billing_cycles'=>[['tenure_type'=>'REGULAR','frequency'=>['interval_unit'=>'MONTH','interval_count'=>1],
            'total_cycles'=>0,'pricing_scheme'=>['fixed_price'=>['value'=>number_format($amount/100,2,'.',''),'currency_code'=>'USD']]]]];
    }
    public function testCheckoutUsesServerSelectedAlphaAndResumesSameAgreement(): void
    {
        $user=$this->member(true);$calls=0;$created=[];
        $client=$this->createMock(PayPalClient::class);
        $client->method('request')->willReturnCallback(function($method,$path,$body=null,$requestId=null)use(&$calls,&$created){
            if ($path==='/v1/billing/plans/P-ALPHA')return $this->plan(499);
            if ($method==='POST') {
                $calls++;$created=$body;
                $this->assertSame('P-ALPHA',$body['plan_id']);$this->assertSame($body['custom_id'],$requestId);
                return ['id'=>'I-TEST','links'=>[['rel'=>'approve','href'=>'https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=test']]];
            }
            return ['id'=>'I-TEST','plan_id'=>'P-ALPHA','custom_id'=>$created['custom_id'],'status'=>'APPROVAL_PENDING'];
        });
        $billing=new PayPalBilling($client);$first=$billing->checkout($user);$second=$billing->checkout($user);
        $this->assertSame($first,$second);$this->assertSame(1,$calls);
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
    }
    public function testCheckoutTimeoutRetriesSameRequestId(): void
    {
        $user=$this->member();$ids=[];$client=$this->createMock(PayPalClient::class);
        $client->method('request')->willReturnCallback(function($method,$path,$body=null,$requestId=null)use(&$ids){
            if ($method==='GET')return $this->plan(999);
            $ids[]=$requestId;if(count($ids)===1)throw new RuntimeException('Simulated timeout');
            return ['id'=>'I-TEST','links'=>[['rel'=>'approve','href'=>'https://www.sandbox.paypal.com/approve']]];
        });
        $billing=new PayPalBilling($client);
        try {$billing->checkout($user);}catch(RuntimeException){}
        $billing->checkout($user);
        $this->assertCount(2,$ids);$this->assertSame($ids[0],$ids[1]);
        $this->assertSame(1,$this->db->table('subscriptions')->countAllResults());
    }
    public function testBillingRoutesRequireCsrfAndMissingSignaturesAreRejected(): void
    {
        $this->actingAs($this->member());
        foreach (['checkout','refresh','cancel'] as $action) {
            try { $this->post('/billing/paypal/'.$action); $this->fail('Missing CSRF accepted'); }
            catch (\CodeIgniter\Security\Exceptions\SecurityException $e) { $this->assertSame(403,$e->getCode()); }
        }
        $this->withBody(json_encode($this->event()))->post('/webhooks/paypal')->assertStatus(400);
    }
    private function enableTrials(): void
    {
        foreach (['standardTrialPlanId'=>'P-TRIALSTANDARD','alphaTrialPlanId'=>'P-TRIALALPHA','trialHashKey'=>str_repeat('a',64)] as $key=>$value) {
            if (!array_key_exists($key,$this->original)) { $this->original[$key]=config('PayPal')->$key; } config('PayPal')->$key=$value;
        }
    }
    private function trialDetails(array $row): array
    {
        $details=$this->details($row);
        unset($details['billing_info']['last_payment']);
        $details['start_time']=gmdate('c',time()-3600);
        $details['subscriber']=['payer_id'=>'PAYER-ONE'];
        $details['billing_info']['next_billing_time']=gmdate('c',time()-3600+14*86400);
        $details['billing_info']['cycle_executions']=[['tenure_type'=>'TRIAL','sequence'=>1,'total_cycles'=>1]];
        return $details;
    }
    public function testTrialActivationCancellationExpiryAndPaidConversion(): void
    {
        $this->enableTrials(); $user=$this->member(); $row=$this->subscription($user);
        $this->db->table('subscriptions')->where('id',$row['id'])->update(['trial_requested'=>1]);
        $details=$this->trialDetails($row);
        $billing=new PayPalBilling($this->client($details));
        $billing->webhook([],$this->event('WH-TRIAL','BILLING.SUBSCRIPTION.ACTIVATED'));
        $billing->webhook([],$this->event('WH-TRIAL','BILLING.SUBSCRIPTION.ACTIVATED'));
        $this->assertSame('trialing',(new SubscriptionAccess())->activeFor($user->id)['status']);
        $this->assertSame(1,$this->db->table('trial_claims')->where('kind','payer')->countAllResults());
        $this->actingAs($user);
        foreach (['/tools/qr','/tools/gang-sheet','/tools/image-studio'] as $route) { $this->get($route)->assertStatus(200); }
        $this->get('/account/subscription')->assertSee('Your 14-day trial is active.');
        $details['status']='CANCELLED'; unset($details['billing_info']['next_billing_time']);
        (new PayPalBilling($this->client($details)))->refresh($user->id);
        $this->assertEquals(1,(new SubscriptionAccess())->activeFor($user->id)['cancel_at_period_end']);
        $this->db->table('subscriptions')->where('id',$row['id'])->update(['current_period_end'=>gmdate('Y-m-d H:i:s',time()-1),'trial_ends_at'=>gmdate('Y-m-d H:i:s',time()-1)]);
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $failed=$this->trialDetails($row); $failed['billing_info']['failed_payments_count']=1;
        (new PayPalBilling($this->client($failed)))->refresh($user->id);
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        (new PayPalBilling($this->client($this->details($row))))->refresh($user->id);
        $this->assertSame('active',(new SubscriptionAccess())->activeFor($user->id)['status']);
    }
    public function testTrialCannotStartFromApprovalMissingPayerOrExtendedSchedule(): void
    {
        $this->enableTrials(); $user=$this->member(); $row=$this->subscription($user);
        $this->db->table('subscriptions')->where('id',$row['id'])->update(['trial_requested'=>1]);
        foreach (['pending','payer','schedule','cycle'] as $case) {
            $details=$this->trialDetails($row);
            if ($case==='pending') { $details['status']='APPROVED'; }
            if ($case==='payer') { unset($details['subscriber']); }
            if ($case==='schedule') { $details['billing_info']['next_billing_time']=gmdate('c',time()+30*86400); }
            if ($case==='cycle') { unset($details['billing_info']['cycle_executions']); }
            (new PayPalBilling($this->client($details)))->refresh($user->id);
            $this->assertNull((new SubscriptionAccess())->activeFor($user->id),$case);
        }
    }
    public function testRepeatedPayerIsCanceledBeforeTrialAccess(): void
    {
        $this->enableTrials(); $user=$this->member(); $row=$this->subscription($user);
        $this->db->table('subscriptions')->where('id',$row['id'])->update(['trial_requested'=>1]);
        (new \App\Services\TrialEligibility())->claimPayer('PAYER-ONE',999);
        $client=$this->createMock(PayPalClient::class);
        $client->expects($this->exactly(2))->method('request')->willReturnCallback(function($method,$path) use($row) {
            if ($method==='GET') { return $this->trialDetails($row); }
            $this->assertStringEndsWith('/cancel',$path); return [];
        });
        (new PayPalBilling($client))->refresh($user->id);
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $this->assertSame('canceled',(new PayPalBilling())->latest($user->id)['status']);
    }
    public function testTrialEligibilityDeviceReservationAndSharedIp(): void
    {
        $this->enableTrials(); $user=$this->member(); $guard=new \App\Services\TrialEligibility();
        $this->assertTrue($guard->eligible($user));
        $guard->reserve($user,99);
        $this->assertFalse($guard->eligible($user));
        $users=model(UserModel::class);
        $other=new User(['email'=>'other@example.test','password'=>'LongExamplePassword!88','first_name'=>'Other','last_name'=>'Person','active'=>1]);
        $users->save($other); $other=$users->findById($users->getInsertID());
        $this->assertFalse($guard->eligible($other),'Same browser blocks a second account');
        session()->set('trial_device',bin2hex(random_bytes(32)));
        $this->assertTrue($guard->eligible($other),'One shared IP alone does not block a different device');
        for ($n=0;$n<4;$n++) {
            $this->db->table('trial_claims')->insert(['environment'=>'sandbox','kind'=>'user','signal_hash'=>hash('sha256','extra'.$n),
                'subscription_id'=>100+$n,'ip_hash'=>$guard->hash('ip',service('request')->getIPAddress()),'created_at'=>gmdate('Y-m-d H:i:s')]);
        }
        $this->assertFalse($guard->eligible($other),'Network velocity limits trial creation');
    }
    public function testTrialPlanAndCheckoutUseDedicatedPlan(): void
    {
        $this->enableTrials(); $user=$this->member(true);
        $plan=$this->plan(499); $plan['billing_cycles'][0]['sequence']=2;
        array_unshift($plan['billing_cycles'],['tenure_type'=>'TRIAL','sequence'=>1,'frequency'=>['interval_unit'=>'DAY','interval_count'=>14],'total_cycles'=>1]);
        $client=$this->createMock(PayPalClient::class);
        $client->method('request')->willReturnCallback(function($method,$path,$body=null) use($plan) {
            if ($method==='GET') { $this->assertSame('/v1/billing/plans/P-TRIALALPHA',$path); return $plan; }
            $this->assertSame('P-TRIALALPHA',$body['plan_id']);
            return ['id'=>'I-TEST','links'=>[['rel'=>'approve','href'=>'https://www.sandbox.paypal.com/approve']]];
        });
        $billing=new PayPalBilling($client); $billing->checkout($user,true);
        $this->assertEquals(1,$billing->latest($user->id)['trial_requested']);
        $this->assertSame(3,$this->db->table('trial_claims')->countAllResults());
        $this->assertNull((new SubscriptionAccess())->activeFor($user->id));
        $plan['billing_cycles'][0]['frequency']['interval_count']=30;
        $this->expectException(RuntimeException::class); $billing->validatePlan($plan,499,true);
    }
    public function testCheckoutRequiresBillingConsent(): void
    {
        $this->actingAs($this->member());
        $this->post('/billing/paypal/checkout',[csrf_token()=>csrf_hash(),'trial_offer'=>'0'])->assertRedirectTo(site_url('account/subscription'));
        $this->assertStringContainsString('accept the recurring billing terms',session('billing_error'));
        $this->assertSame(0,$this->db->table('subscriptions')->countAllResults());
    }

}
