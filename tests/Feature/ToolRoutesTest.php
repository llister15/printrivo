<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use App\Models\UserModel;
use CodeIgniter\Shield\Entities\User;

final class ToolRoutesTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;
    use AuthenticationTesting;
    protected $namespace = null;
    protected $refresh = true;

    private function member(bool $subscribed = false): User
    {
        $model = model(UserModel::class);
        $user = new User(['email' => 'member@example.test', 'password' => 'B7v!kR4#zP9@dL2$', 'first_name' => 'Test', 'last_name' => 'Member', 'active' => 1]);
        $model->save($user);
        $user = $model->findById($model->getInsertID());
        if ($subscribed) {
            $this->db->table('subscriptions')->insert(['user_id' => $user->id, 'status' => 'active', 'current_period_end' => gmdate('Y-m-d H:i:s', time() + 86400)]);
        }
        $this->actingAs($user);
        return $user;
    }

    public function testPublicSalesAndSignupPages(): void
    {
        foreach (['/' => 'Professional Print Tools.', '/pricing' => '$9.99', '/register' => 'Create your account', '/login' => 'Log in'] as $path => $text) {
            $result = $this->get($path);
            $result->assertStatus(200);
            $result->assertSee($text);
        }
        $this->get('/')->assertSee('Payment setup required');
        $this->get('/')->assertDontSee('Available tools');
        $this->get('/')->assertDontSee('Open app launcher');
    }

    public function testVisitorsCannotOpenDashboardOrDirectToolAliases(): void
    {
        foreach (['/dashboard', '/tools/qr', '/qrcode', '/qrcode/index.php', '/tools/gang-sheet', '/dtf', '/dtf/index.php', '/dtf/builder.php', '/dtf/upload.php', '/tools/image-studio', '/cleanimg', '/cleanimg/index.php', '/files/qr-logo/not-a-logo.png'] as $path) {
            $this->get($path)->assertRedirectTo(site_url('login'));
        }
    }

    public function testRegisteredMembersSeeDashboardButCannotUsePaidTools(): void
    {
        $this->member();
        $this->get('/dashboard')->assertSee('Your account is ready.');
        $this->get('/dashboard')->assertSee('Subscription required');
        $this->get('/dashboard')->assertDontSee('Open app launcher');
        foreach (['/tools/qr', '/dtf/builder.php', '/cleanimg/index.php'] as $path) {
            $this->get($path)->assertRedirectTo(site_url('account/subscription'));
        }
        $this->assertSame(0, $this->db->table('subscriptions')->countAllResults());
    }

    public function testSubscribersRetainCanonicalAndLegacyTools(): void
    {
        $this->member(true);
        $this->get('/dashboard')->assertSee('Open app launcher');
        $this->get('/dashboard')->assertSee('Your Printrivo apps');
        foreach (['/tools/qr' => 'Printrivo QR Generator', '/qrcode/index.php' => 'Printrivo QR Generator', '/tools/gang-sheet' => 'Printrivo Gang Sheet Builder', '/dtf/builder.php' => 'Printrivo Gang Sheet Builder', '/tools/image-studio' => 'Printrivo Image Studio', '/cleanimg/index.php' => 'Printrivo Image Studio'] as $path => $title) {
            $result = $this->get($path);
            $result->assertStatus(200);
            $result->assertSee($title);
            $result->assertSee(str_contains($path, 'image-studio') || str_contains($path, 'cleanimg') ? 'printrivo_light_logo.png' : 'printrivo_dark_logo.png');
        }
        $this->get('/dtf/index.php')->assertRedirectTo(site_url('tools/gang-sheet'));
        $this->get('/dtf/upload.php')->assertStatus(405);
    }

    public function testSubscriptionStatusAndPaidThroughDateControlAccess(): void
    {
        $user = $this->member(true);
        foreach (['canceled', 'past_due', 'unpaid', 'incomplete', 'paused'] as $status) {
            $this->db->table('subscriptions')->where('user_id', $user->id)->update(['status' => $status]);
            $this->get('/tools/qr')->assertRedirectTo(site_url('account/subscription'));
        }
        $this->db->table('subscriptions')->where('user_id', $user->id)->update(['status' => 'active', 'cancel_at_period_end' => 1]);
        $this->get('/tools/qr')->assertStatus(200);
        $this->db->table('subscriptions')->where('user_id', $user->id)->update(['current_period_end' => '2020-01-01 00:00:00']);
        $this->get('/tools/qr')->assertRedirectTo(site_url('account/subscription'));
    }

    public function testDisabledMemberCannotOpenDashboard(): void
    {
        $user = $this->member();
        $user->ban('Account disabled');
        $this->get('/dashboard')->assertRedirectTo(site_url('login'));
    }

    public function testInactiveMemberCannotOpenDashboard(): void
    {
        $user = $this->member();
        $user->active = 0;
        model(UserModel::class)->save($user);
        $this->get('/dashboard')->assertRedirectTo(site_url('login'));
    }

    public function testPrivatePathsAreNotRoutable(): void
    {
        foreach (['/dtf/customers.json', '/app/Legacy/customers.json', '/gangsheet/rate'] as $path) {
            try {
                $this->get($path);
                $this->fail('Private route was accessible: ' . $path);
            } catch (\CodeIgniter\Exceptions\PageNotFoundException $exception) {
                $this->assertSame(404, $exception->getCode());
            }
        }
    }

    public function testCsrfProtectsAuthAndToolPostRoutes(): void
    {
        foreach (['/login', '/register', '/logout', '/tools/qr', '/qrcode/index.php', '/dtf/upload.php', '/api/tools/gang-sheet/inspect'] as $path) {
            try {
                $this->post($path, ['content' => 'test']);
                $this->fail('POST accepted without CSRF: ' . $path);
            } catch (\CodeIgniter\Security\Exceptions\SecurityException $exception) {
                $this->assertSame(403, $exception->getCode());
            }
        }
    }

    public function testQrRendererPreservesPngOutput(): void
    {
        $result = (new \App\Services\QrGenerator())->generate('https://mberdigital.com/');
        $this->assertStringStartsWith('data:image/png;base64,', $result);
        $bytes = base64_decode(substr($result, strpos($result, ',') + 1));
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($bytes, 0, 8));
    }
    public function testRegistrationContinuesToPaymentSetupWithoutGrantingAccess(): void
    {
        $password='J6x!B4v@Q9z#M2k$';
        $this->post('/register',[csrf_token()=>csrf_hash(),'first_name'=>'New','last_name'=>'Member',
            'email'=>'new-member@example.test','password'=>$password,'password_confirm'=>$password,'promo_code'=>' Alpha '])
            ->assertRedirectTo(site_url('account/subscription'));
        $user=model(UserModel::class)->findByCredentials(['email'=>'new-member@example.test']);
        $this->assertNotNull($user);
        $this->assertSame('ALPHA',$user->promo_code);
        $this->assertSame(0,$this->db->table('subscriptions')->where('user_id',$user->id)->countAllResults());
        $this->assertTrue((bool)$user->active);
        // Simulate the fresh authenticator used by the redirect's next HTTP request.
        auth()->logout();
        $this->actingAs($user);
        $page=$this->get('/account/subscription');
        $page->assertStatus(200);
        $page->assertSee('STEP 2 OF 2');
        $this->get('/tools/qr')->assertRedirectTo(site_url('account/subscription'));
        $this->post('/logout',[csrf_token()=>csrf_hash()])->assertRedirect();
        $this->withSession();
        $this->get('/dashboard')->assertRedirectTo(site_url('login'));
        $this->post('/login',[csrf_token()=>csrf_hash(),'email'=>'new-member@example.test','password'=>$password])
            ->assertRedirectTo(site_url('dashboard'));
    }

}
