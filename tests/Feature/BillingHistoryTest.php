<?php
use App\Models\UserModel;
use App\Services\BillingHistory;
use App\Services\PayPalClient;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;

final class BillingHistoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticationTesting;
    protected $namespace=null;
    protected $refresh=true;
    private array $original=[];
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['enabled'=>true,'environment'=>'sandbox','clientId'=>'test','secret'=>'test','webhookId'=>'test','standardPlanId'=>'P-STANDARD','alphaPlanId'=>'P-ALPHA'] as $key=>$value) {
            $this->original[$key]=config('PayPal')->$key; config('PayPal')->$key=$value;
        }
    }
    protected function tearDown(): void
    {
        auth()->logout();
        foreach ($this->original as $key=>$value) { config('PayPal')->$key=$value; }
        parent::tearDown();
    }
    private function member(string $email='history@example.test'): User
    {
        $model=model(UserModel::class); $user=new User(['email'=>$email,'password'=>'RandomTestPassword!2026','first_name'=>'History','last_name'=>'Tester','active'=>1]);
        $model->save($user); return $model->findById($model->getInsertID());
    }
    private function agreement(User $user,string $remote='I-HISTORY',string $environment='sandbox'): int
    {
        $this->db->table('subscriptions')->insert(['user_id'=>$user->id,'provider'=>'paypal','provider_environment'=>$environment,
            'provider_subscription_id'=>$remote,'provider_price_id'=>'P-STANDARD','paypal_reference'=>'owned-reference',
            'status'=>'canceled','created_at'=>gmdate('Y-m-d H:i:s',time()-400*86400)]);
        return (int)$this->db->insertID();
    }
    private function details(string $id='I-HISTORY'): array
    {
        return ['id'=>$id,'plan_id'=>'P-STANDARD','custom_id'=>'owned-reference','create_time'=>gmdate('c',time()-400*86400)];
    }
    private function transaction(string $id,int $days,string $status='COMPLETED'): array
    {
        return ['id'=>$id,'time'=>gmdate('c',time()-$days*86400),'status'=>$status,
            'amount_with_breakdown'=>['gross_amount'=>['value'=>'9.99','currency_code'=>'USD']]];
    }
    public function testHistoryNeedsLoginAndEmptyHistoryIsClear(): void
    {
        $this->get('/account/billing-history')->assertRedirectTo(site_url('login'));
        $this->actingAs($this->member());
        $this->get('/account/subscription')->assertSee('Billing history');
        $this->get('/account/billing-history')->assertSee('No billing history yet.');
    }
    public function testCanceledSubscriptionCanReadSortedPaymentsAndOlderRanges(): void
    {
        $user=$this->member(); $id=$this->agreement($user);
        $client=$this->createMock(PayPalClient::class);
        $client->method('request')->willReturnCallback(function($method,$path) {
            if (!str_contains($path,'/transactions')) { return $this->details(); }
            parse_str(parse_url($path,PHP_URL_QUERY),$query);
            $this->assertLessThanOrEqual(90*86400,strtotime($query['end_time'])-strtotime($query['start_time']));
            if (strtotime($query['end_time'])<time()-89*86400) { return ['transactions'=>[$this->transaction('OLD',120)]]; }
            return ['transactions'=>[$this->transaction('PAID',30),$this->transaction('REFUND',2,'REFUNDED')]];
        });
        $history=(new BillingHistory($client))->forUser($user->id,$id);
        $this->assertNull($history['error']);
        $this->assertSame(['REFUND','PAID'],array_column($history['transactions'],'id'));
        $this->assertSame('Refunded',$history['transactions'][0]['status']);
        $this->assertSame(999,$history['transactions'][0]['amount']);
        $older=(new BillingHistory($client))->forUser($user->id,$id,$history['older']);
        $this->assertSame('OLD',$older['transactions'][0]['id']);
        $this->assertSame($history['start'],$older['end']);
        $this->assertNotNull($older['newer']);
        \Config\Services::injectMock('paypalClient',$client);
        $this->actingAs($user);
        $page=$this->get('/account/billing-history');
        $page->assertSee('Paid'); $page->assertSee('Refunded'); $page->assertSee('Older payments');
        $fragment=$this->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->get('/account/billing-history');
        $fragment->assertSee('PayPal subscription payments');
        $fragment->assertDontSee('<!doctype html>');
        $this->assertStringContainsString('no-store',$fragment->response()->getHeaderLine('Cache-Control'));
        $this->assertSame('canceled',$this->db->table('subscriptions')->where('id',$id)->get()->getRowArray()['status']);
    }
    public function testOtherUsersAndLiveAgreementsAreNeverFetched(): void
    {
        $user=$this->member(); $other=$this->member('other-history@example.test');
        $foreign=$this->agreement($other); $live=$this->agreement($user,'I-LIVE','live');
        $client=$this->createMock(PayPalClient::class); $client->expects($this->never())->method('request');
        foreach ([$foreign,$live] as $id) {
            try { (new BillingHistory($client))->forUser($user->id,$id); $this->fail('Foreign agreement exposed'); }
            catch (\CodeIgniter\Exceptions\PageNotFoundException) {}
        }
        $this->assertSame([],(new BillingHistory($client))->forUser($user->id)['subscriptions']);
    }
    public function testOldAgreementsRemainSelectable(): void
    {
        $user=$this->member(); $old=$this->agreement($user,'I-OLD'); $new=$this->agreement($user,'I-NEW');
        $client=$this->createMock(PayPalClient::class);
        $client->method('request')->willReturnCallback(fn($method,$path)=>str_contains($path,'/transactions')?['transactions'=>[]]:$this->details('I-OLD'));
        $history=(new BillingHistory($client))->forUser($user->id,$old);
        $this->assertCount(2,$history['subscriptions']); $this->assertSame($old,(int)$history['selected']['id']);
        $this->assertNull($history['error']);
    }
    public function testOwnershipMismatchAndProviderFailureShowUnavailableNotEmpty(): void
    {
        $user=$this->member(); $this->agreement($user);
        $client=$this->createMock(PayPalClient::class);
        $client->expects($this->once())->method('request')->willReturn($this->details('I-OTHER'));
        $this->assertNotNull((new BillingHistory($client))->forUser($user->id)['error']);
        $client=$this->createMock(PayPalClient::class);
        $client->method('request')->willThrowException(new RuntimeException('Network failure'));
        $this->assertNotNull((new BillingHistory($client))->forUser($user->id)['error']);
    }
    public function testPaginationDeduplicatesAndRefusesExternalLinks(): void
    {
        $user=$this->member(); $this->agreement($user); $calls=0;
        $client=$this->createMock(PayPalClient::class);
        $client->method('request')->willReturnCallback(function($method,$path)use(&$calls) {
            if (!str_contains($path,'/transactions')) { return $this->details(); }
            $calls++;
            return $calls===1 ? ['transactions'=>[$this->transaction('ONE',1)],'total_items'=>2,'links'=>[
                ['rel'=>'next','href'=>'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-HISTORY/transactions?page=2']]]
                : ['transactions'=>[$this->transaction('ONE',1),$this->transaction('TWO',2)],'total_items'=>2];
        });
        $this->assertCount(2,(new BillingHistory($client))->forUser($user->id)['transactions']);
        $client=$this->createMock(PayPalClient::class);
        $client->method('request')->willReturnCallback(fn($method,$path)=>str_contains($path,'/transactions')?
            ['transactions'=>[],'links'=>[['rel'=>'next','href'=>'https://evil.example/steal']]]:$this->details());
        $this->assertNotNull((new BillingHistory($client))->forUser($user->id)['error']);
    }
}
