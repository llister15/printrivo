<?php
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Services\SignupNotifier;
final class SignupNotificationTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;
    protected $namespace=null;
    protected $refresh=true;
    protected function tearDown(): void { auth()->logout(); parent::tearDown(); }
    private function signup(): array
    {
        return [csrf_token()=>csrf_hash(),'first_name'=>'New','last_name'=>'Member','email'=>'signup-notify@example.test',
            'password'=>'CleverLongExample!5678','password_confirm'=>'CleverLongExample!5678'];
    }
    public function testSuccessfulSignupNotifiesOnce(): void
    {
        $notifier=$this->createMock(SignupNotifier::class);
        $notifier->expects($this->once())->method('send')->with($this->callback(fn($user)=>$user->id>0 && $user->email==='signup-notify@example.test'))->willReturn(true);
        \Config\Services::injectMock('signupNotifier',$notifier);
        $this->post('/register',$this->signup())->assertRedirect();
        $this->assertSame(1,$this->db->table('users')->countAllResults());
    }
    public function testInvalidSignupDoesNotNotify(): void
    {
        $notifier=$this->createMock(SignupNotifier::class);$notifier->expects($this->never())->method('send');
        \Config\Services::injectMock('signupNotifier',$notifier);
        $data=$this->signup();$data['email']='invalid';
        $this->post('/register',$data)->assertRedirect();
        $this->assertSame(0,$this->db->table('users')->countAllResults());
    }
    public function testMailFailureDoesNotBlockSignup(): void
    {
        $notifier=$this->createMock(SignupNotifier::class);$notifier->method('send')->willThrowException(new RuntimeException('SMTP failure'));
        \Config\Services::injectMock('signupNotifier',$notifier);
        $this->post('/register',$this->signup())->assertRedirectTo(site_url('account/subscription'));
        $this->assertSame(1,$this->db->table('users')->countAllResults());
    }
}
