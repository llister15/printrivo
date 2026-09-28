<?php
use App\Models\UserModel;
use App\Services\PrintUploadTicket;
use App\Services\RecaptchaVerifier;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class PrintUploadAuthorizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait,FeatureTestTrait,AuthenticationTesting;
    protected $namespace=null;
    protected $refresh=true;
    private string $id='7d62b550-106f-4a91-b338-e45215e68f81';
    private array $original=[];
    protected function setUp(): void
    {
        parent::setUp();
        foreach(['enabled'=>true,'siteKey'=>'test-site','secretKey'=>'test-secret','hostname'=>'example.com'] as $key=>$value){
            $this->original[$key]=config('Recaptcha')->$key;config('Recaptcha')->$key=$value;
        }
        session()->remove('print_upload_tickets');
    }
    protected function tearDown(): void
    {
        foreach($this->original as $key=>$value)config('Recaptcha')->$key=$value;
        session()->remove('print_upload_tickets');auth()->logout();parent::tearDown();
    }
    private function member(bool $subscribed=true): User
    {
        $model=model(UserModel::class);$user=new User(['email'=>'upload@example.test','password'=>'LongExamplePassword!99','first_name'=>'Upload','last_name'=>'Member','active'=>1]);
        $model->save($user);$user=$model->findById($model->getInsertID());
        if($subscribed)$this->db->table('subscriptions')->insert(['user_id'=>$user->id,'provider'=>'test','status'=>'active','current_period_end'=>gmdate('Y-m-d H:i:s',time()+86400)]);
        $user->addGroup('admin');$this->actingAs($user);return $user;
    }
    public function testSubscriberCannotAuthorizePrintUploadDuringAdminTesting(): void
    {
        $user=$this->member();$user->removeGroup('admin');auth()->logout();$this->actingAs($user);
        $verifier=$this->createMock(RecaptchaVerifier::class);$verifier->method('verify')->willReturn(true);
        \Config\Services::injectMock('recaptchaVerifier',$verifier);
        $this->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->post('/api/print-orders/authorize',[
            'request_id'=>$this->id,'g-recaptcha-response'=>'verified',csrf_token()=>csrf_hash(),
        ])->assertStatus(403);
        $this->assertEmpty(session('print_upload_tickets'));
    }
    public function testMissingOrFailedCaptchaCannotIssueAuthorization(): void
    {
        $this->member();
        $verifier=$this->createMock(RecaptchaVerifier::class);$verifier->method('verify')->willReturn(false);\Config\Services::injectMock('recaptchaVerifier',$verifier);
        foreach([[],['g-recaptcha-response'=>'failed']] as $fields){
            $this->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->post('/api/print-orders/authorize',$fields+['request_id'=>$this->id,csrf_token()=>csrf_hash()])->assertStatus(403);
        }
        $this->assertEmpty(session('print_upload_tickets'));
    }
    public function testFreshVerificationAuthorizesOnlyOneUploadWithoutSecondCaptchaCheck(): void
    {
        $this->member();config('CswPrint')->token='test-print-token';
        $verifier=$this->createMock(RecaptchaVerifier::class);
        $verifier->expects($this->once())->method('verify')->with('fresh-token','form_submit')->willReturn(true);
        \Config\Services::injectMock('recaptchaVerifier',$verifier);
        $grant=$this->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->post('/api/print-orders/authorize',[
            csrf_token()=>csrf_hash(),'request_id'=>$this->id,'g-recaptcha-response'=>'fresh-token']);
        $grant->assertOK();$ticket=json_decode($grant->response()->getBody(),true)['upload_ticket'];
        $headers=['X-Requested-With'=>'XMLHttpRequest','X-Printrivo-Upload-Ticket'=>$ticket];
        // Authorization passes to the upload controller, which reports the missing file.
        $upload=$this->withSession()->withHeaders($headers)->post('/api/print-orders',['request_id'=>$this->id,csrf_token()=>csrf_hash()]);
        $this->assertSame(413,$upload->response()->getStatusCode(),$upload->response()->getBody());
        $this->withSession()->withHeaders($headers)->post('/api/print-orders',['request_id'=>$this->id,csrf_token()=>csrf_hash()])->assertStatus(403);
        $this->assertSame(0,$this->db->table('print_orders')->countAllResults());
    }
    public function testTicketIsBoundToUserRequestSessionAndExpiry(): void
    {
        $tickets=new PrintUploadTicket();$secret=$tickets->issue(12,$this->id);
        $this->assertFalse($tickets->consume(13,$this->id,$secret));
        $this->assertFalse($tickets->consume(12,'wrong-id',$secret));
        $this->assertTrue($tickets->consume(12,$this->id,$secret));
        $this->assertFalse($tickets->consume(12,$this->id,$secret));
        $secret=$tickets->issue(12,$this->id);$stored=session('print_upload_tickets');
        foreach($stored as &$ticket)$ticket['expires']=time()-1;
        session()->set('print_upload_tickets',$stored);
        $this->assertFalse($tickets->consume(12,$this->id,$secret));
        $secret=$tickets->issue(12,$this->id);session()->remove('print_upload_tickets');
        $this->assertFalse($tickets->consume(12,$this->id,$secret));
    }
    public function testAdminAuthorizationFinishesMissingPrintStorageBeforeUpload(): void
    {
        $user=$this->member();$user->addGroup('admin');
        \Config\Database::forge($this->db)->dropTable('print_orders',true);$this->db->resetDataCache();
        $verifier=$this->createMock(RecaptchaVerifier::class);$verifier->method('verify')->willReturn(true);
        \Config\Services::injectMock('recaptchaVerifier',$verifier);
        $response=$this->post('/api/print-orders/authorize',[csrf_token()=>csrf_hash(),'request_id'=>$this->id,'g-recaptcha-response'=>'fresh-token']);
        $response->assertOK();$response->assertJSONFragment(['ok'=>true]);
        $this->db->resetDataCache();$this->assertTrue($this->db->tableExists('print_orders'));
        $this->assertSame(0,$this->db->table('print_orders')->countAllResults());
    }
    public function testSubscriptionIsStillRequiredToAuthorize(): void
    {
        $this->member(false);
        $verifier=$this->createMock(RecaptchaVerifier::class);$verifier->method('verify')->willReturn(true);\Config\Services::injectMock('recaptchaVerifier',$verifier);
        $this->post('/api/print-orders/authorize',[csrf_token()=>csrf_hash(),'request_id'=>$this->id,'g-recaptcha-response'=>'fresh-token'])->assertStatus(403);
        $this->assertEmpty(session('print_upload_tickets'));
    }
}
