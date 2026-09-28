<?php
use App\Services\RecaptchaVerifier;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class RecaptchaTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;
    protected $namespace=null;
    protected $refresh=true;
    private array $original=[];
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['enabled'=>true,'siteKey'=>'public-test-key','secretKey'=>'private-test-key','hostname'=>'example.com','minimumScore'=>0.5] as $key=>$value) {
            $this->original[$key]=config('Recaptcha')->$key; config('Recaptcha')->$key=$value;
        }
    }
    protected function tearDown(): void
    {
        foreach ($this->original as $key=>$value) { config('Recaptcha')->$key=$value; }
        parent::tearDown();
    }
    public function testEveryBrowserPostAndLegacyAliasRejectsMissingToken(): void
    {
        foreach (['register','login','logout','support/bug-report','billing/paypal/checkout','billing/paypal/refresh','billing/paypal/cancel',
            'tools/qr','qrcode','qrcode/index.php','api/tools/gang-sheet/inspect','dtf/upload.php'] as $path) {
            $this->post('/'.$path,[csrf_token()=>csrf_hash()])->assertStatus(403);
        }
        $this->assertSame(0,$this->db->table('users')->countAllResults());
        $this->assertSame(0,$this->db->table('subscriptions')->countAllResults());
    }
    public function testValidCaptchaContinuesToNormalRegistrationValidation(): void
    {
        $verifier=$this->createMock(RecaptchaVerifier::class);
        $verifier->expects($this->once())->method('verify')->with('fresh-token','form_submit')->willReturn(true);
        \Config\Services::injectMock('recaptchaVerifier',$verifier);
        $this->post('/register',[csrf_token()=>csrf_hash(),'g-recaptcha-response'=>'fresh-token'])->assertRedirect();
        $this->assertSame(0,$this->db->table('users')->countAllResults());
    }
    public function testUploadActionAndJsonErrors(): void
    {
        $verifier=$this->createMock(RecaptchaVerifier::class);
        $verifier->expects($this->once())->method('verify')->with('upload-token','artwork_upload')->willReturn(false);
        \Config\Services::injectMock('recaptchaVerifier',$verifier);
        $result=$this->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->post('/dtf/upload.php',[
            csrf_token()=>csrf_hash(),'g-recaptcha-response'=>'upload-token']);
        $result->assertStatus(403); $result->assertJSONFragment(['ok'=>false]);
    }
    public function testGoogleClaimsMustMatchDomainActionScoreAndFreshness(): void
    {
        $verifier=new RecaptchaVerifier();
        $valid=['success'=>true,'hostname'=>'example.com','action'=>'form_submit','score'=>0.9,'challenge_ts'=>gmdate('c')];
        $this->assertTrue($verifier->accepts($valid,'form_submit'));
        foreach (['success'=>false,'hostname'=>'evil.example','action'=>'artwork_upload','score'=>0.1,'challenge_ts'=>gmdate('c',time()-180)] as $key=>$value) {
            $invalid=$valid; $invalid[$key]=$value;
            $this->assertFalse($verifier->accepts($invalid,'form_submit'),$key);
        }
        $this->assertFalse($verifier->accepts([],'form_submit'));
        $this->assertFalse($verifier->verify('','form_submit'));
    }
    public function testMisconfigurationFailsClosedAndNeverExposesSecret(): void
    {
        config('Recaptcha')->siteKey='';
        $this->post('/login',[csrf_token()=>csrf_hash(),'g-recaptcha-response'=>'anything'])->assertStatus(503);
        $page=$this->get('/register');
        $page->assertDontSee('private-test-key');
    }
    public function testWebhookDoesNotRequireBrowserCaptcha(): void
    {
        config('Recaptcha')->siteKey='';
        $this->withBody('{}')->post('/webhooks/paypal')->assertStatus(400);
    }
    public function testPagesLoadTheSharedClientOnlyWhenEnabled(): void
    {
        $this->get('/register')->assertSee('public-test-key');
        $this->get('/login')->assertSee('assets/js/recaptcha.js');
        config('Recaptcha')->enabled=false;
        $this->get('/login')->assertDontSee('assets/js/recaptcha.js');
    }
}
