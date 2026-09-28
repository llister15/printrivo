<?php
use App\Models\UserModel;
use App\Services\PrintOrder;
use App\Services\CswPrintClient;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
final class PrintOrderTest extends CIUnitTestCase
{
    use DatabaseTestTrait,FeatureTestTrait,AuthenticationTesting;
    protected $namespace=null;
    protected $refresh=true;
    private string $id='7d62b550-106f-4a91-b338-e45215e68f81';
    private function member(): User
    {
        $model=model(UserModel::class);
        $user=new User(['email'=>'printer@example.test','password'=>'LongExamplePassword!99','first_name'=>'Print','last_name'=>'Customer','active'=>1]);
        $model->save($user);return $model->findById($model->getInsertID());
    }
    private function png(): string { return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9C0AAAAASUVORK5CYII='); }
    private function payload(User $user,string $tool='image-studio'): array
    {
        return (new PrintOrder())->payload($user,['fulfillment'=>'pickup','confirmed_price_cents'=>'1500','request_id'=>$this->id,'tool'=>$tool,'dpi'=>'300','filename'=>'test.png'],$this->png());
    }
    protected function tearDown(): void { auth()->logout();parent::tearDown(); }
    public function testOnlyAdminsCanSendAndTokenIsNeverInPage(): void
    {
        $fields=[csrf_token()=>csrf_hash()];
        $this->post('/api/print-orders',$fields)->assertStatus(401);
        $user=$this->member();$this->actingAs($user);
        $this->post('/api/print-orders',$fields)->assertStatus(403);
        $this->db->table('subscriptions')->insert(['user_id'=>$user->id,'provider'=>'test','status'=>'active','current_period_end'=>gmdate('Y-m-d H:i:s',time()+86400)]);
        $this->post('/api/print-orders',$fields)->assertStatus(403);
        foreach(['/tools/image-studio','/tools/gang-sheet'] as $path){
            $page=$this->get($path);$page->assertOK();
            $this->assertDoesNotMatchRegularExpression('/<button[^>]+id="(?:sendPrint|send-print)"/', $page->getBody());
        }
        $user->addGroup('admin');$this->actingAs($user);
        config('CswPrint')->token='';
        $this->post('/api/print-orders',$fields)->assertStatus(503);
        config('CswPrint')->token='private-test-token';
        foreach(['/tools/image-studio','/tools/gang-sheet'] as $path){
            $page=$this->get($path);$page->assertOK();$page->assertSee('Send to printer');$page->assertDontSee('private-test-token');
        }
    }
    public function testPayloadIncludesOriginalArtworkCustomerAndDimensions(): void
    {
        $user=$this->member();
        foreach(['image-studio','gang-sheet'] as $tool){
            $payload=$this->payload($user,$tool);
            $this->assertSame('Print Customer',$payload['customer_name']);
            $this->assertSame('printer@example.test',$payload['email']);
            $this->assertSame($this->png(),base64_decode($payload['designs'][0]['artwork'][0]['data_base64']));
            $this->assertSame(['Units'=>1],$payload['designs'][0]['garments'][0]['sizes']);
            $this->assertStringContainsString('1 × 1 pixels; 300 DPI',$payload['production_specs']);
            $this->assertStringContainsString('member-'.$user->id,$payload['external_id']);
        }
    }
    public function testPricingAndShippingAreValidatedOnServer(): void
    {
        $user=$this->member(); $orders=new PrintOrder();
        $this->assertSame('15.00',$this->payload($user)['designs'][0]['garments'][0]['print_price']);
        $canvas=imagecreatetruecolor(3000,6000);
        ob_start();imagepng($canvas);$png=ob_get_clean();imagedestroy($canvas);
        $input=['request_id'=>$this->id,'tool'=>'image-studio','dpi'=>'150','fulfillment'=>'shipping',
            'shipping_address'=>'Print Customer, 123 Main St, Springfield, IL 62701, USA','confirmed_price_cents'=>'2400'];
        $payload=$orders->payload($user,$input,$png);
        $this->assertSame('24.00',$payload['designs'][0]['garments'][0]['print_price']);
        $this->assertStringContainsString('Shipping requested. Ship to: Print Customer',$payload['notes']);
        $this->assertStringContainsString('Shipping quoted separately',$payload['notes']);
        foreach ([['confirmed_price_cents'=>'1'],['shipping_address'=>''],['fulfillment'=>'invalid'],['confirmed_price_cents'=>null]] as $change) {
            try { $orders->payload($user,array_replace($input,$change),$png);$this->fail('Expected invalid confirmation'); }
            catch (InvalidArgumentException $error) { $this->assertSame(422,$error->getCode()); }
        }
    }

    public function testSuccessIsCachedAndChangedRetryIsRejected(): void
    {
        $user=$this->member();$payload=$this->payload($user);$orders=new PrintOrder();
        $client=$this->createMock(CswPrintClient::class);
        $client->expects($this->once())->method('send')->with($payload)->willReturn(['status'=>201,'body'=>['order_id'=>12,'order_number'=>'FP-12']]);
        \Config\Services::injectMock('cswPrintClient',$client);
        $this->assertFalse($orders->send($user->id,$this->id,$payload)['duplicate']);
        $this->assertTrue($orders->send($user->id,$this->id,$payload)['duplicate']);
        $payload['job_name']='Changed';
        $this->expectExceptionCode(409);$orders->send($user->id,$this->id,$payload);
    }
    public function testUncertainFailureRetriesIdenticalPayload(): void
    {
        $user=$this->member();$payload=$this->payload($user);$orders=new PrintOrder();$calls=0;
        $client=$this->createMock(CswPrintClient::class);
        $client->expects($this->exactly(2))->method('send')->willReturnCallback(function($sent)use($payload,&$calls){
            $this->assertSame($payload,$sent);
            if(++$calls===1)throw new RuntimeException('timeout');
            return ['status'=>200,'body'=>['order_id'=>12,'order_number'=>'FP-12','duplicate'=>true]];
        });
        \Config\Services::injectMock('cswPrintClient',$client);
        try{$orders->send($user->id,$this->id,$payload);$this->fail('Expected timeout');}catch(RuntimeException $e){$this->assertSame('timeout',$e->getMessage());}
        $this->assertTrue($orders->send($user->id,$this->id,$payload)['duplicate']);
    }
    public function testRateLimitWaitIsPersisted(): void
    {
        $user=$this->member();$payload=$this->payload($user);$orders=new PrintOrder();
        $client=$this->createMock(CswPrintClient::class);
        $client->expects($this->once())->method('send')->willReturn(['status'=>429,'body'=>[],'retry_after'=>'120']);
        \Config\Services::injectMock('cswPrintClient',$client);
        for($i=0;$i<2;$i++){
            try{$orders->send($user->id,$this->id,$payload);$this->fail('Expected rate limit');}catch(InvalidArgumentException $e){$this->assertSame(429,$e->getCode());}
        }
    }
    public function testFilePayloadUsesDigestAndStreamsThroughClient(): void
    {
        $user=$this->member();$temp=tempnam(sys_get_temp_dir(),'print-order');
        file_put_contents($temp,$this->png());
        try {
            $orders=new PrintOrder();
            $payload=$orders->payload($user,['fulfillment'=>'pickup','confirmed_price_cents'=>'1500','request_id'=>$this->id,'tool'=>'image-studio','dpi'=>'300','filename'=>'artwork.png'],$temp,true);
            $art=$payload['designs'][0]['artwork'][0];
            $this->assertSame('artwork',$art['upload_field']);
            $this->assertSame(hash_file('sha256',$temp),$art['sha256']);
            $this->assertArrayNotHasKey('data_base64',$art);
            $client=$this->createMock(CswPrintClient::class);
            $client->expects($this->once())->method('send')->with($payload,$temp)->willReturn(['status'=>201,'body'=>['order_id'=>13,'order_number'=>'FP-13']]);
            \Config\Services::injectMock('cswPrintClient',$client);
            $this->assertSame('FP-13',$orders->send($user->id,$this->id,$payload,$temp)['order_number']);
        } finally {unlink($temp);}
    }
    public function testRejectsInvalidArtworkAndUnsupportedDpi(): void
    {
        $user=$this->member();$orders=new PrintOrder();
        foreach([['dpi'=>'72','png'=>$this->png()],['dpi'=>'300','png'=>'not a png'],['dpi'=>'300','png'=>str_repeat('x',8388609)]] as $case){
            try{$orders->payload($user,['fulfillment'=>'pickup','confirmed_price_cents'=>'1500','request_id'=>$this->id,'tool'=>'image-studio','dpi'=>$case['dpi']],$case['png']);$this->fail('Expected invalid artwork');}
            catch(InvalidArgumentException $e){$this->assertContains($e->getCode(),[413,422]);}
        }
    }
}
