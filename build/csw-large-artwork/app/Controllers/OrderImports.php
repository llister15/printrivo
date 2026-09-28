<?php

namespace App\Controllers;

use App\Libraries\JsonOrderImport;
use App\Libraries\DtfOrderImport;
use App\Models\AppSettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class OrderImports extends BaseController {
	public function index(): RedirectResponse|string {
		if ( ! session()->get( 'user_id' ) ) { return redirect()->withCookies()->to( site_url( 'login' ) ); }
		if ( ! $this->userHasPermission( 'orders_create' ) ) { return $this->permissionDenied( 'import orders' ); }
		return $this->form();
	}

	public function template( bool $dtf = false ): RedirectResponse|ResponseInterface|string {
		if ( ! session()->get( 'user_id' ) ) { return redirect()->withCookies()->to( site_url( 'login' ) ); }
		if ( ! $this->userHasPermission( 'orders_create' ) ) { return $this->permissionDenied( 'import orders' ); }
		return $this->response->setContentType( 'application/json' )
			->setHeader( 'Content-Disposition', 'attachment; filename="' . ( $dtf ? 'dtf-order-import.json' : 'order-import.json' ) . '"' )
			->setBody( $this->example( $dtf ) );
	}

	public function import(): RedirectResponse|string {
		if ( ! session()->get( 'user_id' ) ) { return redirect()->withCookies()->to( site_url( 'login' ) ); }
		if ( ! $this->userHasPermission( 'orders_create' ) ) { return $this->permissionDenied( 'import orders' ); }
		$json = $this->request->getPost( 'order_json' );
		$json = is_string( $json ) ? trim( $json ) : '';
		try {
			$file = $this->request->getFile( 'order_file' );
			if ( $file && $file->getError() !== UPLOAD_ERR_NO_FILE ) {
				if ( $json !== '' ) { throw new \InvalidArgumentException( 'Paste JSON or upload a file, not both.', 422 ); }
				if ( ! $file->isValid() || strtolower( $file->getClientExtension() ) !== 'json' ) {
					throw new \InvalidArgumentException( 'Upload a valid .json file.', 422 );
				}
				if ( $file->getSize() > DtfOrderImport::MAX_BYTES ) { throw new \InvalidArgumentException( 'JSON must be 16 MB or smaller.', 413 ); }
				$json = (string) file_get_contents( $file->getTempName() );
			}
			$importer = new JsonOrderImport();
			$isDtf = property_exists( (object) json_decode( $json ), 'designs' );
			$result = $importer->create( $isDtf ? ( new DtfOrderImport() )->parse( $json ) : $importer->parse( $json ) );
			return redirect()->withCookies()->to( site_url( 'orders/' . $result['order_id'] ) )->with( 'success', $result['duplicate']
				? 'This order was already imported. Opened the existing order.' : 'Order imported into Sales Review.' );
		} catch ( \InvalidArgumentException $exception ) {
			$this->response->setStatusCode( $exception->getCode() ?: 422 );
			return $this->form( strlen( $json ) <= DtfOrderImport::MAX_BYTES ? $json : '', $exception->getMessage() );
		} catch ( \Throwable $exception ) {
			log_message( 'error', 'JSON order import failed: {type}', array( 'type' => get_class( $exception ) ) );
			$this->response->setStatusCode( 500 );
			return $this->form( strlen( $json ) <= DtfOrderImport::MAX_BYTES ? $json : '', 'The order could not be saved. Please try again.' );
		}
	}

	public function dtfTemplate(): RedirectResponse|ResponseInterface|string { return $this->template( true ); }
	public function dtfWebhook(): ResponseInterface { return $this->receiveWebhook( true ); }
	public function webhook(): ResponseInterface { return $this->receiveWebhook( false ); }

	private function receiveWebhook( bool $dtf ): ResponseInterface {
		$prefix = $dtf ? 'dtf_order_webhook' : 'json_order_webhook';
		$this->response->setHeader( 'Cache-Control', 'no-store' );
		$settings = ( new AppSettingModel() )->getValues( array( $prefix . '_enabled', $prefix . '_token_hash' ) );
		if ( ( $settings[$prefix . '_enabled'] ?? '' ) !== '1' ) {
			return $this->error( 403, 'Order webhook is disabled.' );
		}
		$hash = (string) ( $settings[$prefix . '_token_hash'] ?? '' );
		$token = trim( $this->request->getHeaderLine( 'X-FlowPress-Token' ) );
		if ( $hash === '' || $token === '' || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			return $this->error( 401, 'Invalid webhook token.' );
		}
		if ( ! service( 'throttler' )->check( 'json-orders-' . hash( 'sha256', $hash ), 120, 60 ) ) {
			$this->response->setHeader( 'Retry-After', '60' );
			return $this->error( 429, 'Too many requests. Retry in 60 seconds.' );
		}
		$type = strtolower( trim( explode( ';', $this->request->getHeaderLine( 'Content-Type' ) )[0] ) );
		$multipart = $dtf && $type === 'multipart/form-data';
		if ( $type !== 'application/json' && ! $multipart ) { return $this->error( 415, $dtf ? 'Use application/json or multipart/form-data.' : 'Use Content-Type: application/json.' ); }
		$limit = $multipart ? DtfOrderImport::MAX_ARTWORK_BYTES + 1048576 : ( $dtf ? DtfOrderImport::MAX_BYTES : JsonOrderImport::MAX_BYTES );
		if ( (int) $this->request->getHeaderLine( 'Content-Length' ) > $limit ) { return $this->error( 413, $multipart ? 'Artwork must be 500 MB or smaller.' : 'Order JSON exceeds the size limit.' ); }
		try {
			$importer = new JsonOrderImport();
			$uploads = array();
			if ( $multipart ) {
				$body = $this->request->getPost( 'order' );
				$file = $this->request->getFile( 'artwork' );
				if ( ! is_string( $body ) || ! $file || ! $file->isValid() ) { return $this->error( 413, 'The artwork upload did not complete. Check the server upload limits.' ); }
				if ( strlen( $body ) > JsonOrderImport::MAX_BYTES || $file->getSize() > DtfOrderImport::MAX_ARTWORK_BYTES ) { return $this->error( 413, 'Artwork must be at most 500 MB and order metadata at most 256 KB.' ); }
				$uploads['artwork'] = $file->getTempName();
			} else { $body = (string) $this->request->getBody(); }
			$result = $importer->create( $dtf ? ( new DtfOrderImport() )->parse( $body, $uploads ) : $importer->parse( $body ) );
			$result['order_url'] = site_url( 'orders/' . $result['order_id'] );
			return $this->response->setStatusCode( $result['duplicate'] ? 200 : 201 )->setJSON( $result );
		} catch ( \InvalidArgumentException $exception ) {
			return $this->error( $exception->getCode() ?: 422, $exception->getMessage() );
		} catch ( \Throwable $exception ) {
			log_message( 'error', 'JSON order webhook failed: {type}', array( 'type' => get_class( $exception ) ) );
			return $this->error( 500, 'The order could not be saved. Retry with the same source and external_id.' );
		}
	}

	public function dtfSettings(): RedirectResponse|string { return $this->settings( true ); }
	public function saveDtfSettings(): RedirectResponse|string { return $this->saveSettings( true ); }

	public function settings( bool $dtf = false ): RedirectResponse|string {
		$prefix = $dtf ? 'dtf_order_webhook' : 'json_order_webhook';
		if ( ! session()->get( 'user_id' ) ) { return redirect()->withCookies()->to( site_url( 'login' ) ); }
		if ( ! $this->userHasPermission( 'settings_users' ) ) { return $this->permissionDenied( 'manage webhook settings' ); }
		$this->response->setHeader( 'Cache-Control', 'no-store' );
		return view( 'account/order_webhook', array(
			'title' => ( $dtf ? 'DTF Builder Webhook' : 'JSON Order Webhook' ) . ' | FlowPress',
			'enabled' => ( new AppSettingModel() )->getValue( $prefix . '_enabled', '0' ) === '1',
			'webhookUrl' => site_url( $dtf ? 'webhooks/dtf/orders' : 'webhooks/orders' ),
			'webhookName' => $dtf ? 'DTF Builder Webhook' : 'JSON Order Webhook',
			'settingsPath' => $dtf ? 'settings/dtf-webhook' : 'settings/order-webhook',
			'templatePath' => $dtf ? 'orders/import/dtf-template' : 'orders/import/template',
			'isDtf' => $dtf,
			'newToken' => session()->getFlashdata( $prefix . '_new_token' ),
		) );
	}

	public function saveSettings( bool $dtf = false ): RedirectResponse|string {
		$prefix = $dtf ? 'dtf_order_webhook' : 'json_order_webhook';
		$settingsPath = $dtf ? 'settings/dtf-webhook' : 'settings/order-webhook';
		if ( ! session()->get( 'user_id' ) ) { return redirect()->withCookies()->to( site_url( 'login' ) ); }
		if ( ! $this->userHasPermission( 'settings_users' ) ) { return $this->permissionDenied( 'manage webhook settings' ); }
		$model = new AppSettingModel();
		$action = $this->request->getPost( 'webhook_action' );
		if ( $action === 'disable' ) {
			$model->setValue( $prefix . '_enabled', '0' );
			return redirect()->withCookies()->to( site_url( $settingsPath ) )->with( 'success', 'Order webhook disabled.' );
		}
		if ( $action !== 'generate' ) {
			return redirect()->withCookies()->to( site_url( $settingsPath ) )->with( 'error', 'Choose a webhook action.' );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$model->setValues( array( $prefix . '_token_hash' => hash( 'sha256', $token ), $prefix . '_enabled' => '1' ) );
		return redirect()->withCookies()->to( site_url( $settingsPath ) )
			->with( $prefix . '_new_token', $token )->with( 'success', 'Order webhook enabled. Copy the new token below; it is shown only once.' );
	}

	private function form( string $json = '', string $error = '' ): string {
		return view( 'orders/import', array(
			'title' => 'Import JSON Order | FlowPress', 'json' => $json, 'importError' => $error,
			'example' => $this->example(), 'canManageWebhook' => $this->userHasPermission( 'settings_users' ),
		) );
	}

	private function example( bool $dtf = false ): string {
		return $dtf ? \App\Config\OrderImportTemplates::DTF : \App\Config\OrderImportTemplates::BASIC;
	}
	private function error( int $status, string $message ): ResponseInterface { return $this->response->setStatusCode( $status )->setJSON( array( 'ok' => false, 'error' => $message ) ); }
}
