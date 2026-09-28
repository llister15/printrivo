<?php

use App\Controllers\OrderImports;
use App\Database\Migrations\AddJsonOrderImportKeys;
use App\Libraries\JsonOrderImport;
use App\Libraries\OrderTiming;
use App\Models\AppSettingModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;

final class JsonOrderWebhookTest extends CIUnitTestCase {
	private string $token = 'unit-test-token-not-a-real-credential';
	protected function setUp(): void {
		parent::setUp();
		require_once APPPATH . 'Database/Migrations/2026-09-19-000002_AddJsonOrderImportKeys.php';
		$db = db_connect( 'tests' );
		$this->assertSame( 'SQLite3', $db->DBDriver );
		$this->assertSame( ':memory:', $db->database );
		$data = ( new JsonOrderImport() )->parse( $this->example() );
		$fields = array_merge( array_keys( $data ), array_keys( OrderTiming::initialTiming() ), array( 'order_number', 'created_at', 'updated_at', 'order_designs', 'print_placements' ) );
		$columns = array( 'id INTEGER PRIMARY KEY AUTOINCREMENT' );
		foreach ( array_unique( $fields ) as $field ) {
			if ( ! str_starts_with( $field, 'import_' ) ) { $columns[] = $field . ' TEXT'; }
		}
		$db->query( 'CREATE TABLE ' . $db->prefixTable( 'orders' ) . ' (' . implode( ', ', $columns ) . ')' );
		$db->query( 'CREATE TABLE ' . $db->prefixTable( 'app_settings' ) . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key TEXT UNIQUE, setting_value TEXT, created_at TEXT, updated_at TEXT)' );
		( new AddJsonOrderImportKeys() )->up();
		( new AppSettingModel() )->setValues( array( 'json_order_webhook_enabled' => '1', 'json_order_webhook_token_hash' => hash( 'sha256', $this->token ) ) );
		session()->remove( 'user_id' );
	}
	protected function tearDown(): void {
		$db = db_connect( 'tests' );
		foreach ( $db->table( 'orders' )->get()->getResultArray() as $order ) {
            foreach ( json_decode( $order['uploaded_files'] ?? '[]', true ) ?: array() as $file ) {
                $path = WRITEPATH . 'uploads/' . $file['path'];
                if ( str_contains( $file['path'], 'orders/import-' ) ) { @unlink( $path ); @rmdir( dirname( $path ) ); }
            }
        }
        $db->query( 'DROP TABLE ' . $db->prefixTable( 'orders' ) );
		$db->query( 'DROP TABLE ' . $db->prefixTable( 'app_settings' ) );
		$db->resetDataCache();
		session()->remove( array( 'user_id', 'permissions', 'json_order_webhook_new_token', 'success', 'error' ) );
		parent::tearDown();
	}
	private function example(): string { return file_get_contents( ROOTPATH . 'docs/templates/order-import.json' ); }
	private function controller( string $body = '', array $headers = array(), array $post = array(), bool $allowed = true, $upload = null ): OrderImports {
		$request = $this->getMockBuilder( IncomingRequest::class )->disableOriginalConstructor()->onlyMethods( array( 'getBody', 'getHeaderLine', 'getPost', 'getFile' ) )->getMock();
		$request->method( 'getBody' )->willReturn( $body );
		$request->method( 'getHeaderLine' )->willReturnCallback( static fn( $key ) => $headers[$key] ?? '' );
		$request->method( 'getPost' )->willReturnCallback( static fn( $key ) => $post[$key] ?? null );
		$request->method( 'getFile' )->willReturn( $upload );
		$controller = $this->getMockBuilder( OrderImports::class )->onlyMethods( array( 'userHasPermission' ) )->getMock();
		$controller->method( 'userHasPermission' )->willReturn( $allowed );
		$controller->initController( $request, new Response( config( 'App' ) ), service( 'logger' ) );
		return $controller;
	}
	private function send( ?string $body = null, ?array $headers = null ): Response {
		return $this->controller( $body ?? $this->example(), $headers ?? array( 'Content-Type' => 'application/json', 'X-FlowPress-Token' => $this->token ) )->webhook();
	}
    public function testImportPageAndDownloadsUseBundledRuntimeTemplates(): void {
        session()->set(array('user_id'=>1,'permissions'=>array()));
        $this->assertStringContainsString('Import JSON Order', $this->controller()->index());
        $basic = $this->controller()->template()->getBody();
        $dtf = $this->controller()->dtfTemplate()->getBody();
        $this->assertSame(\App\Config\OrderImportTemplates::BASIC, $basic);
        $this->assertSame(\App\Config\OrderImportTemplates::DTF, $dtf);
        $this->assertSame(json_decode($this->example(), true), json_decode($basic, true));
        $this->assertSame(json_decode(file_get_contents(ROOTPATH . 'docs/templates/dtf-order-import.json'), true), json_decode($dtf, true));
        $this->assertSame(10, (new JsonOrderImport())->parse($basic)['quantity']);
        $this->assertSame(44, (new \App\Libraries\DtfOrderImport())->parse($dtf)['quantity']);
    }

	public function testCreateRetryAndConflictingRetry(): void {
		$response = $this->send();
		$this->assertSame( 201, $response->getStatusCode() );
		$created = json_decode( $response->getBody(), true );
		$this->assertFalse( $created['duplicate'] );
		$row = db_connect( 'tests' )->table( 'orders' )->get()->getRowArray();
		$this->assertSame( 'sales_review', $row['status'] );
		$this->assertEquals( 134.88, $row['price_balance'] );
		$this->assertNotEmpty( $row['current_department_started_at'] );
		$response = $this->send();
		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertTrue( json_decode( $response->getBody(), true )['duplicate'] );
		$changed = json_decode( $this->example(), true );
		$changed['customer_name'] = 'Changed Customer';
		$this->assertSame( 409, $this->send( json_encode( $changed ) )->getStatusCode() );
		$this->assertSame( 1, db_connect( 'tests' )->table( 'orders' )->countAllResults() );
	}
	public function testAuthenticationValidationAndDisabledWebhookDoNotCreateOrders(): void {
		$this->assertSame( 401, $this->send( null, array() )->getStatusCode() );
		$this->assertSame( 401, $this->send( null, array( 'X-FlowPress-Token' => 'wrong' ) )->getStatusCode() );
		$this->assertSame( 415, $this->send( null, array( 'X-FlowPress-Token' => $this->token ) )->getStatusCode() );
		$this->assertSame( 400, $this->send( '{broken' )->getStatusCode() );
		$this->assertSame( 422, $this->send( '{}' )->getStatusCode() );
		$this->assertSame( 413, $this->send( str_repeat( ' ', JsonOrderImport::MAX_BYTES + 1 ) )->getStatusCode() );
		( new AppSettingModel() )->setValue( 'json_order_webhook_enabled', '0' );
		$this->assertSame( 403, $this->send()->getStatusCode() );
		$this->assertSame( 0, db_connect( 'tests' )->table( 'orders' )->countAllResults() );
	}
	public function testManualPasteSharesDuplicateProtection(): void {
		$this->send();
		session()->set( array( 'user_id' => 1, 'permissions' => array() ) );
		$response = $this->controller( '', array(), array( 'order_json' => $this->example() ) )->import();
		$this->assertStringContainsString( '/orders/1', $response->getHeaderLine( 'Location' ) );
		$this->assertSame( 1, db_connect( 'tests' )->table( 'orders' )->countAllResults() );
		$this->assertStringContainsString( 'already imported', session()->getFlashdata( 'success' ) );
	}
	public function testSignedOutImportRedirectsAndAdminGeneratesHashedToken(): void {
		$this->assertStringContainsString( '/login', $this->controller()->import()->getHeaderLine( 'Location' ) );
		session()->set( array( 'user_id' => 1, 'permissions' => array() ) );
		$this->controller( '', array(), array( 'webhook_action' => 'generate' ) )->saveSettings();
		$token = session()->getFlashdata( 'json_order_webhook_new_token' );
		$this->assertSame( 64, strlen( $token ) );
		$this->assertSame( hash( 'sha256', $token ), ( new AppSettingModel() )->getValue( 'json_order_webhook_token_hash' ) );
		$this->assertSame( 401, $this->send()->getStatusCode() );
		$this->assertSame( 201, $this->send( null, array( 'X-FlowPress-Token' => $token, 'Content-Type' => 'application/json' ) )->getStatusCode() );
	}
    public function testSalesViewRendersImportedDesignDataAndExistingEditor(): void {
        $model = new \App\Models\OrderModel();
        $fields = (new ReflectionProperty($model, 'allowedFields'))->getValue($model);
        $order = array_merge(array_fill_keys($fields, ''), (new \App\Libraries\DtfOrderImport())->parse(file_get_contents(ROOTPATH . 'docs/templates/dtf-order-import.json')), array('id'=>999,'order_number'=>'TEST-DTF','created_at'=>'2026-09-19 10:00:00','updated_at'=>'2026-09-19 10:00:00'));
        session()->set(array('user_id'=>1,'permissions'=>array('all')));
        $html = view('orders/show', array('order'=>$order, 'uploadedFiles'=>array(), 'placementFiles'=>array(), 'designProofFiles'=>array(), 'printReadyFiles'=>array(), 'users'=>array(), 'proofUrl'=>'','invoiceUrl'=>'','timingSummary'=>OrderTiming::summarize($order),'taxRatePercent'=>0,'itemTypes'=>array('Shirt'),'productionNoteTemplates'=>array(),'availablePickupBins'=>array()));
        $this->assertStringContainsString('data-order-designs-builder', $html);
        $this->assertStringContainsString('data-placement-builder', $html);
        $this->assertStringContainsString('value="black-shirts"', $html);
        $this->assertStringContainsString('value="Gildan 5000"', html_entity_decode($html));
        $this->assertStringContainsString('Convenience Fee (Credit Card)', $html);
        $this->assertStringNotContainsString('Convenience Fee (4%', $html);
        $this->assertStringContainsString('assets/js/order-designs.js', $html);
    }

    public function testDtfWebhookPersistsArtworkAndRetryDoesNotDuplicate(): void {
        (new AppSettingModel())->setValues(array('dtf_order_webhook_enabled'=>'1', 'dtf_order_webhook_token_hash'=>hash('sha256','dtf-test-token')));
        $body = file_get_contents(ROOTPATH . 'docs/templates/dtf-order-import.json');
        $headers = array('Content-Type'=>'application/json','X-FlowPress-Token'=>'dtf-test-token');
        $this->assertSame(401, $this->controller($body, array('X-FlowPress-Token'=>$this->token))->dtfWebhook()->getStatusCode());
        $response = $this->controller($body, $headers)->dtfWebhook();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $row = db_connect('tests')->table('orders')->get()->getRowArray();
        $files = json_decode($row['uploaded_files'], true);
        $this->assertCount(2, $files);
        foreach ($files as $file) { $this->assertFileExists(WRITEPATH . 'uploads/' . $file['path']); }
        $this->assertSame(68, \App\Libraries\OrderDesigns::summary(\App\Libraries\OrderDesigns::fromOrder($row))['prints']);
        $this->assertSame(200, $this->controller($body, $headers)->dtfWebhook()->getStatusCode());
        session()->set(array('user_id'=>1,'permissions'=>array()));
        $this->assertStringContainsString('/orders/1', $this->controller('', array(), array('order_json'=>$body))->import()->getHeaderLine('Location'));
        $this->assertSame(1, db_connect('tests')->table('orders')->countAllResults());
        $this->assertSame($files, json_decode(db_connect('tests')->table('orders')->get()->getRowArray()['uploaded_files'], true));
    }

	public function testMigrationHasUniqueImportKeyAndCanBeRepeated(): void {
		( new AddJsonOrderImportKeys() )->up();
		$indexes = db_connect( 'tests' )->getIndexData( 'orders' );
		$this->assertSame( 'UNIQUE', $indexes['orders_import_key_unique']->type );
		$this->send();
		$row = db_connect( 'tests' )->table( 'orders' )->get()->getRowArray();
		unset( $row['id'] );
		$this->expectException( \CodeIgniter\Database\Exceptions\DatabaseException::class );
		db_connect( 'tests' )->table( 'orders' )->insert( $row );
	}

    public function testLargeMultipartArtworkIsStoredIntactAndRetryDoesNotDuplicate(): void {
        (new AppSettingModel())->setValues(['dtf_order_webhook_enabled'=>'1','dtf_order_webhook_token_hash'=>hash('sha256','dtf-large-token')]);
        $temp=tempnam(sys_get_temp_dir(),'csw-large-test');
        $chunk=static fn($type,$data)=>pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
        $png="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',1800,1800,8,2,0,0,0))
            .$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\x28\x80\xff",1800),1800),0)).$chunk('IEND','');
        file_put_contents($temp,$png);unset($png);
        try {
            $payload=json_decode(file_get_contents(ROOTPATH.'docs/templates/dtf-order-import.json'),true);
            $payload['designs'][0]['artwork'][0]=['id'=>'logo','name'=>'large.png','upload_field'=>'artwork','sha256'=>hash_file('sha256',$temp)];
            $upload=$this->getMockBuilder(\CodeIgniter\HTTP\Files\UploadedFile::class)->disableOriginalConstructor()->onlyMethods(['isValid','getSize','getTempName'])->getMock();
            $upload->method('isValid')->willReturn(true);$upload->method('getSize')->willReturn(filesize($temp));$upload->method('getTempName')->willReturn($temp);
            $headers=['Content-Type'=>'multipart/form-data; boundary=test','X-FlowPress-Token'=>'dtf-large-token'];
            $post=['order'=>json_encode($payload)];
            $response=$this->controller('', $headers, $post, true, $upload)->dtfWebhook();
            $this->assertSame(201,$response->getStatusCode(),$response->getBody());
            $receipt=json_decode($response->getBody(),true);
            $order=db_connect('tests')->table('orders')->where('id',$receipt['order_id'])->get()->getRowArray();
            $files=json_decode($order['uploaded_files'],true);
            $this->assertSame(filesize($temp),filesize(WRITEPATH.'uploads/'.$files[0]['path']));
            $this->assertSame(hash_file('sha256',$temp),hash_file('sha256',WRITEPATH.'uploads/'.$files[0]['path']));
            $retry=$this->controller('', $headers, $post, true, $upload)->dtfWebhook();
            $this->assertSame(200,$retry->getStatusCode());$this->assertTrue(json_decode($retry->getBody(),true)['duplicate']);
            $this->assertSame(1,db_connect('tests')->table('orders')->countAllResults());
            $payload['designs'][0]['artwork'][0]['sha256']=str_repeat('0',64);
            $this->assertSame(422,$this->controller('', $headers, ['order'=>json_encode($payload)], true, $upload)->dtfWebhook()->getStatusCode());
            $this->assertSame(413,$this->controller('', $headers, $post)->dtfWebhook()->getStatusCode());
        } finally {unlink($temp);}
    }
}
