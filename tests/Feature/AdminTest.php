<?php
use App\Models\UserModel;
use App\Services\PayPalClient;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
final class AdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticationTesting;
    protected $namespace=null;
    protected $refresh=true;
    private function user(string $email,bool $admin=false): User
    {
        $model=model(UserModel::class);
        $user=new User(['email'=>$email,'password'=>'LongExamplePassword!99','first_name'=>'Test','last_name'=>'Member','active'=>1]);
        $model->save($user); $user=$model->findById($model->getInsertID());
        if ($admin) { $user->addGroup('admin'); }
        return $user;
    }
    protected function tearDown(): void { auth()->logout(); parent::tearDown(); }
    public function testInvitationPasswordCanBeCorrectedAndAcceptedOnlyOnce(): void
    {
        $member=$this->user('invited@example.test');
        $token=bin2hex(random_bytes(32));
        $this->db->table('member_invitations')->insert([
            'user_id'=>$member->id,'token_hash'=>hash('sha256',$token),
            'created_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600),
        ]);
        $password='Unique!Invitation9Password';
        foreach ([[$password,'different'],['short','short']] as [$first,$confirm]) {
            $result=$this->post('/account/setup',[
                csrf_token()=>csrf_hash(),'token'=>$token,'password'=>$first,'password_confirm'=>$confirm,
            ]);
            $result->assertStatus(422);
            $body=$result->response()->getBody();
            $this->assertStringContainsString('value="'.$token.'"',$body);
            $this->assertStringNotContainsString('value="'.$first.'"',$body);
            $this->assertStringContainsString('no-store',$result->response()->getHeaderLine('Cache-Control'));
            $this->assertNull($this->db->table('member_invitations')->where('user_id',$member->id)->get()->getRowArray()['accepted_at']);
        }
        $data=['token'=>$token,'password'=>$password,'password_confirm'=>$password];
        $this->post('/account/setup',$data+[csrf_token()=>csrf_hash()])->assertRedirectTo(site_url('login'));
        $this->assertTrue(password_verify($password,model(UserModel::class)->findById($member->id)->password_hash));
        $this->post('/account/setup',$data+[csrf_token()=>csrf_hash()])->assertRedirectTo(site_url('account/setup'));
        $this->assertStringContainsString('invalid, expired, or already used',session('setup_error'));
    }
    public function testAdminRoutesRejectVisitorsAndMembers(): void
    {
        $this->get('/admin')->assertRedirectTo(site_url('login'));
        $member=$this->user('member@example.test'); $this->actingAs($member);
        $this->get('/admin')->assertStatus(403);
        $this->get('/admin/users/'.$member->id)->assertStatus(403);
        $this->post('/admin/users/'.$member->id.'/cancel',[csrf_token()=>csrf_hash(),'confirm'=>'1','subscription_id'=>'1'])->assertStatus(403);
        $this->assertSame(0,$this->db->table('admin_actions')->countAllResults());
    }
    public function testAdminCanSearchUsersAndCountUniqueEligibleAccounts(): void
    {
        $admin=$this->user('owner@example.test',true); $member=$this->user('customer@example.test');
        foreach ([1,2] as $n) { $this->db->table('subscriptions')->insert(['user_id'=>$member->id,'provider'=>'test','status'=>'active','current_period_end'=>gmdate('Y-m-d H:i:s',time()+86400)]); }
        $this->actingAs($admin);
        $result=$this->get('/admin'); $result->assertOK(); $result->assertSee('Active members');
        $this->assertStringContainsString('<span>Active members</span><strong>1</strong>',$result->response()->getBody());
        $this->get('/admin?q=customer')->assertSee('customer@example.test');
        $this->get('/admin?q=nonexistent')->assertSee('No users match');
        $this->get('/admin/users/'.$member->id)->assertOK();
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->get('/admin/users/999999');
    }
    public function testUsageShowsSuccessfulLoginsOnlyAndRecordsMemberActivity(): void
    {
        $admin=$this->user('owner@example.test',true);
        $member=$this->user('usage@example.test');
        $this->actingAs($member);
        $this->get('/dashboard')->assertOK();
        $this->assertNotNull($this->db->table('users')->where('id',$member->id)->get()->getRowArray()['last_active']);
        $recent=gmdate('Y-m-d H:i:s',time()-3600);
        foreach ([[1,$recent],[1,gmdate('Y-m-d H:i:s',time()-40*86400)],[0,gmdate('Y-m-d H:i:s')]] as [$success,$date]) {
            $this->db->table('auth_logins')->insert(['user_id'=>$member->id,'success'=>$success,'date'=>$date,
                'ip_address'=>'127.0.0.1','id_type'=>'email_password','identifier'=>'usage@example.test']);
        }
        $this->actingAs($admin);
        $page=$this->get('/admin?q=usage');
        $page->assertOK();
        $page->assertSee('1 successful logins in the last 30 days');
        $page->assertSee($recent);
        $page->assertSee('Last activity:');
        $this->get('/admin?q=owner')->assertSee('0 successful logins in the last 30 days');
    }
    private function agreement(User $user): int
    {
        $this->db->table('subscriptions')->insert(['user_id'=>$user->id,'provider'=>'paypal','provider_environment'=>config('PayPal')->environment,
            'provider_subscription_id'=>'I-TEST','provider_price_id'=>'P-STANDARD','paypal_reference'=>'ref-test',
            'billing_amount'=>999,'billing_currency'=>'USD','status'=>'active','current_period_end'=>gmdate('Y-m-d H:i:s',time()+86400)]);
        return (int)$this->db->insertID();
    }
    public function testCancellationRequiresConfirmationAndCurrentAgreement(): void
    {
        $admin=$this->user('owner@example.test',true);$member=$this->user('customer@example.test');$id=$this->agreement($member);
        $client=$this->createMock(PayPalClient::class);$client->expects($this->never())->method('request');
        \Config\Services::injectMock('paypalClient',$client);$this->actingAs($admin);
        foreach ([['subscription_id'=>(string)$id],['subscription_id'=>(string)($id+1),'confirm'=>'1']] as $data) {
            $this->post('/admin/users/'.$member->id.'/cancel',$data+[csrf_token()=>csrf_hash()])->assertRedirect();
        }
        $this->assertSame(0,$this->db->table('admin_actions')->countAllResults());
    }
    public function testConfirmedCancellationIsAuditedAndPreservesAccess(): void
    {
        $admin=$this->user('owner@example.test',true);$member=$this->user('customer@example.test');$id=$this->agreement($member);
        $canceled=false; $client=$this->createMock(PayPalClient::class);
        $client->expects($this->exactly(3))->method('request')->willReturnCallback(function($method,$path) use (&$canceled) {
            if ($method==='POST') { $this->assertStringEndsWith('/I-TEST/cancel',$path);$canceled=true;return []; }
            return ['id'=>'I-TEST','plan_id'=>'P-STANDARD','custom_id'=>'ref-test','status'=>$canceled?'CANCELLED':'ACTIVE',
                'billing_info'=>['last_payment'=>['time'=>gmdate('c',time()-3600),'amount'=>['value'=>'9.99','currency_code'=>'USD']],
                'failed_payments_count'=>0,'outstanding_balance'=>['value'=>'0.00']]];
        });
        \Config\Services::injectMock('paypalClient',$client);$this->actingAs($admin);
        $this->post('/admin/users/'.$member->id.'/cancel',[csrf_token()=>csrf_hash(),'subscription_id'=>(string)$id,'confirm'=>'1'])->assertRedirect();
        $audit=$this->db->table('admin_actions')->get()->getRowArray();
        $this->assertSame('confirmed',$audit['outcome']);$this->assertEquals($admin->id,$audit['admin_id']);
        $row=$this->db->table('subscriptions')->where('id',$id)->get()->getRowArray();
        $this->assertEquals(1,$row['cancel_at_period_end']);
        $this->assertNotNull((new \App\Services\SubscriptionAccess())->activeFor($member->id));
    }
    public function testProviderFailureIsNotReportedAsSuccess(): void
    {
        $admin=$this->user('owner@example.test',true);$member=$this->user('customer@example.test');$id=$this->agreement($member);
        $client=$this->createMock(PayPalClient::class);$client->method('request')->willThrowException(new RuntimeException('timeout'));
        \Config\Services::injectMock('paypalClient',$client);$this->actingAs($admin);
        $this->post('/admin/users/'.$member->id.'/cancel',[csrf_token()=>csrf_hash(),'subscription_id'=>(string)$id,'confirm'=>'1'])->assertRedirect();
        $this->assertSame('unconfirmed',$this->db->table('admin_actions')->get()->getRowArray()['outcome']);
        $this->assertStringContainsString('could not be confirmed',session('admin_error'));
    }
}
