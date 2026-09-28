<?php
use App\Models\UserModel;
use App\Services\BugReportMailer;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class BugReportTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticationTesting;
    protected $namespace=null;
    protected $refresh=true;
    private string $recipient;
    private string $from;
    private bool $captcha;
    protected function setUp(): void
    {
        parent::setUp();
        $this->recipient=config('BugReports')->recipient; config('BugReports')->recipient='support@example.test';
        $this->from=config('Email')->fromEmail; config('Email')->fromEmail='sender@example.test';
        $this->captcha=config('Recaptcha')->enabled; config('Recaptcha')->enabled=false;
        \Config\Services::injectMock('throttler',new class { public function check(...$args) { return true; } });
    }
    protected function tearDown(): void
    {
        auth()->logout();
        config('BugReports')->recipient=$this->recipient; config('Email')->fromEmail=$this->from;
        config('Recaptcha')->enabled=$this->captcha;
        parent::tearDown();
    }
    private function member(): User
    {
        $users=model(UserModel::class); $user=new User(['email'=>'bug-member@example.test','password'=>'ExampleTestPassword!99','first_name'=>'Bug','last_name'=>'Reporter','active'=>1]);
        $users->save($user); $user=$users->findById($users->getInsertID()); $this->actingAs($user); return $user;
    }
    private function fields(): array
    {
        return [csrf_token()=>csrf_hash(),'tool'=>'tools/image-studio','summary'=>'Export problem','description'=>'I opened an image and clicked export, but nothing downloaded.','expected'=>'A PNG download.'];
    }
    public function testReportRequiresAuthenticationAndCsrf(): void
    {
        $this->post('/support/bug-report',$this->fields())->assertStatus(401);
        $this->member();
        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->post('/support/bug-report',['summary'=>'Missing token']);
    }
    public function testValidatedReportUsesMemberIdentityAndReturnsSuccess(): void
    {
        $user=$this->member();
        $mailer=$this->createMock(BugReportMailer::class);
        $mailer->expects($this->once())->method('send')->willReturnCallback(function($member,$app,$summary,$description,$expected)use($user) {
            $this->assertSame($user->id,$member->id); $this->assertSame('tools/image-studio',$app['route']);
            $this->assertSame('Export problem',$summary); return true;
        });
        \Config\Services::injectMock('bugReportMailer',$mailer);
        $this->post('/support/bug-report',$this->fields()+['email'=>'attacker@example.test','recipient'=>'attacker@example.test'])->assertJSONFragment(['ok'=>true]);
    }
    public function testInvalidToolAndOverlongTextDoNotSend(): void
    {
        $this->member(); $mailer=$this->createMock(BugReportMailer::class); $mailer->expects($this->never())->method('send');
        \Config\Services::injectMock('bugReportMailer',$mailer);
        foreach ([['tool'=>'https://example.com'],['description'=>'short'],['summary'=>str_repeat('x',121)]] as $change) {
            $this->post('/support/bug-report',array_replace($this->fields(),$change))->assertStatus(422);
        }
    }
    public function testMailFailureAndMissingConfigurationNeverClaimSuccess(): void
    {
        $this->member(); $mailer=$this->createMock(BugReportMailer::class); $mailer->method('send')->willReturn(false);
        \Config\Services::injectMock('bugReportMailer',$mailer);
        $this->post('/support/bug-report',$this->fields())->assertStatus(503);
        config('Email')->fromEmail='';
        $this->post('/support/bug-report',$this->fields())->assertJSONFragment(['ok'=>false]);
    }
    public function testRateLimitStopsSending(): void
    {
        $this->member();
        \Config\Services::injectMock('throttler',new class { public function check(...$args) { return false; } });
        $mailer=$this->createMock(BugReportMailer::class); $mailer->expects($this->never())->method('send');
        \Config\Services::injectMock('bugReportMailer',$mailer);
        $this->post('/support/bug-report',$this->fields())->assertStatus(429);
    }
    public function testDialogOnEveryToolAndScrollButtonOnlyOnContentPages(): void
    {
        $user=$this->member();
        $this->db->table('subscriptions')->insert(['user_id'=>$user->id,'provider'=>'test','status'=>'active','current_period_end'=>gmdate('Y-m-d H:i:s',time()+86400)]);
        foreach (['/tools/qr','/tools/gang-sheet','/tools/image-studio'] as $path) {
            $page=$this->get($path); $page->assertSee('Report a bug'); $page->assertSee('printrivo-bug-dialog');
            $page->assertDontSee('printrivo-back-to-top');
        }
        $this->get('/dashboard')->assertSee('printrivo-back-to-top');
        $this->get('/pricing')->assertSee('printrivo-back-to-top');
    }
}
