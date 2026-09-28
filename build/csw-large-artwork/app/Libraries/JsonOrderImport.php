<?php

namespace App\Libraries;

use App\Models\OrderModel;
use InvalidArgumentException;
use RuntimeException;

/** Shared, strictly validated input for manual imports and incoming webhooks. */
final class JsonOrderImport {
	public const MAX_BYTES = 262144;

	public function parse( string $json ): array {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			throw new InvalidArgumentException( 'JSON must be 256 KB or smaller.', 413 );
		}
		try {
			$payload = json_decode( $json, false, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			throw new InvalidArgumentException( 'Invalid JSON: ' . $exception->getMessage(), 400 );
		}
		if ( ! $payload instanceof \stdClass ) {
			throw new InvalidArgumentException( 'Send one JSON order object, not an array.', 422 );
		}
		return $this->normalize( $payload );
	}

	public function normalize( \stdClass $payload ): array {
		$this->keys( $payload, array( 'source', 'external_id', 'customer_name', 'company_name', 'email', 'phone', 'job_name', 'needed_by', 'needed_by_time', 'design_needed', 'notes', 'production_specs', 'items', 'pricing' ), 'order' );
		$source = $this->text( $payload, 'source', 64, true );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $source ) ) {
			throw new InvalidArgumentException( 'source must contain lowercase letters, numbers, hyphens or underscores.', 422 );
		}
		$externalId = $this->text( $payload, 'external_id', 120, true );
		$data = array(
			'import_source' => $source,
			'import_external_id' => $externalId,
			'import_key' => hash( 'sha256', $source . "\0" . $externalId ),
			'customer_name' => $this->text( $payload, 'customer_name', 160, true ),
			'company_name' => $this->text( $payload, 'company_name', 160 ),
			'email' => $this->text( $payload, 'email', 190 ),
			'phone' => $this->text( $payload, 'phone', 40 ),
			'job_name' => $this->text( $payload, 'job_name', 190, true ),
			'needed_by' => $this->text( $payload, 'needed_by', 10 ) ?: null,
			'needed_by_time' => $this->text( $payload, 'needed_by_time', 5 ) ?: null,
			'design_needed' => $this->text( $payload, 'design_needed', 10 ) ?: 'unsure',
			'notes' => htmlspecialchars( $this->text( $payload, 'notes', 10000 ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
			'production_specs' => htmlspecialchars( $this->text( $payload, 'production_specs', 10000 ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
		);
		if ( $data['email'] !== '' && ! filter_var( $data['email'], FILTER_VALIDATE_EMAIL ) ) {
			throw new InvalidArgumentException( 'email must be a valid email address.', 422 );
		}
		$date = $data['needed_by'] ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $data['needed_by'] ) : null;
		if ( $data['needed_by'] && ( ! $date || $date->format( 'Y-m-d' ) !== $data['needed_by'] || (int) $date->format( 'Y' ) < 1000 ) ) {
			throw new InvalidArgumentException( 'needed_by must be a real date in YYYY-MM-DD format.', 422 );
		}
		if ( $data['needed_by_time'] && ( ! $data['needed_by'] || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $data['needed_by_time'] ) ) ) {
			throw new InvalidArgumentException( 'needed_by_time requires needed_by and must use 24-hour HH:MM format.', 422 );
		}
		if ( ! in_array( $data['design_needed'], array( 'yes', 'no', 'unsure' ), true ) ) {
			throw new InvalidArgumentException( 'design_needed must be yes, no or unsure.', 422 );
		}
		if ( ! isset( $payload->items ) || ! is_array( $payload->items ) || count( $payload->items ) < 1 || count( $payload->items ) > 100 ) {
			throw new InvalidArgumentException( 'items must be an array of 1 to 100 items.', 422 );
		}
		$rows = array();
		$itemCents = $printCents = $quantity = 0;
		$teams = array();
		foreach ( $payload->items as $index => $item ) {
			$path = 'items[' . $index . ']';
			if ( ! $item instanceof \stdClass ) {
				throw new InvalidArgumentException( $path . ' must be an object.', 422 );
			}
			$this->keys( $item, array( 'item_type', 'color', 'category', 'production', 'sizes', 'item_price', 'print_price' ), $path );
			$category = $this->text( $item, 'category', 30 ) ?: 'Men';
			if ( ! in_array( $category, array( 'Men', 'Women', 'Unisex', 'Youth', 'Toddler' ), true ) ) {
				throw new InvalidArgumentException( $path . '.category must be Men, Women, Unisex, Youth or Toddler.', 422 );
			}
			$production = $item->production ?? array( 'print' );
			if ( ! is_array( $production ) || ! $production || count( $production ) > 4 ) {
				throw new InvalidArgumentException( $path . '.production must be a nonempty array.', 422 );
			}
			foreach ( $production as $team ) {
				if ( ! in_array( $team, array( 'print', 'embroidery', 'air_brush', 'outsourced' ), true ) ) {
					throw new InvalidArgumentException( $path . '.production accepts print, embroidery, air_brush or outsourced.', 422 );
				}
				$teams[$team] = true;
			}
			if ( ! ( $item->sizes ?? null ) instanceof \stdClass || count( (array) $item->sizes ) < 1 || count( (array) $item->sizes ) > 40 ) {
				throw new InvalidArgumentException( $path . '.sizes must map size names to quantities.', 422 );
			}
			$itemType = $this->text( $item, 'item_type', 80, true );
			$allowedSizes = $this->sizesForItem( $itemType, $category );
			$sizes = (array) $item->sizes;
			if ( array_diff( array_keys( $sizes ), $allowedSizes ) ) {
				throw new InvalidArgumentException( $path . '.sizes accepts: ' . implode( ', ', $allowedSizes ), 422 );
			}
			foreach ( $sizes as $size => $count ) {
				if ( trim( (string) $size ) === '' || strlen( (string) $size ) > 30 || ! is_int( $count ) || $count < 0 || $count > 10000 ) {
					throw new InvalidArgumentException( $path . '.sizes needs size names up to 30 characters and integer quantities from 0 to 10000.', 422 );
				}
			}
			$rowQuantity = array_sum( $sizes );
			if ( $rowQuantity < 1 || ( $quantity += $rowQuantity ) > 10000 ) {
				throw new InvalidArgumentException( 'Each item needs a positive quantity; the order is limited to 10000 pieces.', 422 );
			}
			$itemPrice = $this->money( $item, 'item_price', $path );
			$printPrice = $this->money( $item, 'print_price', $path );
			$itemCents += $itemPrice * $rowQuantity;
			$printCents += $printPrice * $rowQuantity;
			$rows[] = array(
				'item_type' => $itemType,
				'color' => $this->text( $item, 'color', 80 ),
				'category' => $category, 'production' => array_values( array_unique( $production ) ),
				'item_source' => 'csw_house', 'print_option' => 'front',
				'item_price' => $this->decimal( $itemPrice ), 'print_price' => $this->decimal( $printPrice ), 'sizes' => $sizes,
			);
		}
		$pricing = $payload->pricing ?? new \stdClass();
		if ( ! $pricing instanceof \stdClass ) {
			throw new InvalidArgumentException( 'pricing must be an object.', 422 );
		}
		$this->keys( $pricing, array( 'design', 'setup', 'rush', 'discount', 'tax', 'tax_exempt', 'deposit', 'amount_paid', 'payment_method' ), 'pricing' );
		$money = array();
		foreach ( array( 'design', 'setup', 'rush', 'discount', 'tax', 'deposit', 'amount_paid' ) as $field ) {
			$money[$field] = $this->money( $pricing, $field, 'pricing' );
		}
		$taxExempt = $pricing->tax_exempt ?? false;
		if ( ! is_bool( $taxExempt ) || ( $taxExempt && $money['tax'] > 0 ) ) {
			throw new InvalidArgumentException( 'pricing.tax_exempt must be true or false; exempt orders must have zero tax.', 422 );
		}
		$method = $this->text( $pricing, 'payment_method', 10 );
		if ( ! in_array( $method, array( '', 'cash', 'card' ), true ) ) {
			throw new InvalidArgumentException( 'pricing.payment_method must be cash, card or an empty string.', 422 );
		}
		$subtotal = $itemCents + $printCents + $money['design'] + $money['setup'] + $money['rush'];
		if ( $money['discount'] > $subtotal ) {
			throw new InvalidArgumentException( 'pricing.discount cannot exceed the subtotal.', 422 );
		}
		$base = $subtotal - $money['discount'] + $money['tax'];
		// Stay within existing DECIMAL(10,2) order columns, including card fees.
		if ( $subtotal > 9000000000 || $base > 9000000000 ) {
			throw new InvalidArgumentException( 'Order total is too large.', 422 );
		}
		$data = array_merge( $data, array(
			'requester_type' => 'sales_rep', 'stage' => 'sales', 'status' => 'sales_review', 'design_fee_level' => 'pending',
			'job_type' => $rows[0]['item_type'], 'garment_color' => $rows[0]['color'],
			'quantity' => $quantity, 'color_size_quantities' => json_encode( $rows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ),
			'production_type' => count( $teams ) === 1 ? array_key_first( $teams ) : 'mixed',
			'price_items' => $this->decimal( $itemCents ), 'price_print' => $this->decimal( $printCents ),
			'price_design' => $this->decimal( $money['design'] ), 'price_setup' => $this->decimal( $money['setup'] ),
			'price_rush' => $this->decimal( $money['rush'] ), 'price_discount_type' => 'flat',
			'price_discount' => $this->decimal( $money['discount'] ), 'price_taxes' => $this->decimal( $money['tax'] ),
			'tax_exempt' => (int) $taxExempt, 'payment_method' => $method ?: null,
			'price_deposit' => $this->decimal( $money['deposit'] ), 'price_amount_paid' => $this->decimal( $money['amount_paid'] ),
			'uploaded_files' => '[]', 'design_proof_files' => '[]', 'review_text_enabled' => 0,
		), CardServiceCharge::totals( $base / 100, $method, $money['deposit'] / 100, $money['amount_paid'] / 100 ) );
		if ( $money['deposit'] + $money['amount_paid'] > (int) round( (float) $data['price_total'] * 100 ) ) {
			throw new InvalidArgumentException( 'Deposit plus amount_paid cannot exceed the order total.', 422 );
		}
		$data['import_payload_hash'] = hash( 'sha256', json_encode( $this->canonical( $payload ), JSON_THROW_ON_ERROR ) );
		return $data;
	}

	public function create( array $data ): array {
		$model = new OrderModel();
		$existing = $model->where( 'import_key', $data['import_key'] )->first();
		if ( $existing ) {
			return $this->duplicate( $existing, $data );
		}
		$artwork = $data['_import_artwork'] ?? array();
		unset( $data['_import_artwork'] );
		$written = array();
		$folder = '';
		if ( $artwork ) {
			$folder = 'orders/import-' . bin2hex( random_bytes( 12 ) );
			$path = WRITEPATH . 'uploads/' . $folder;
			if ( ! is_dir( $path ) && ! mkdir( $path, 0775, true ) ) { throw new RuntimeException( 'Could not store imported artwork.', 500 ); }
			$files = array();
			foreach ( $artwork as $image ) {
				$target = $path . '/' . $image['stored_name'];
				if ( isset( $image['temp_path'] )
					? ( ! copy( $image['temp_path'], $target ) || filesize( $target ) !== filesize( $image['temp_path'] ) )
					: file_put_contents( $target, $image['body'] ) === false ) {
					foreach ( $written as $file ) { @unlink( $file ); } @unlink( $target ); @rmdir( $path );
					throw new RuntimeException( 'Could not store imported artwork.', 500 );
				}
				$written[] = $target;
				$files[] = array( 'stored_name' => $image['stored_name'], 'original_name' => $image['original_name'], 'path' => $folder . '/' . $image['stored_name'] );
			}
			$data['uploaded_files'] = json_encode( $files, JSON_THROW_ON_ERROR );
		}
		$cleanup = static function () use ( $written, $folder ): void {
			foreach ( $written as $file ) { @unlink( $file ); }
			if ( $folder !== '' ) { @rmdir( WRITEPATH . 'uploads/' . $folder ); }
		};
		$data['order_number'] = 'FP-' . date( 'Ymd' ) . '-' . strtoupper( bin2hex( random_bytes( 6 ) ) );
		try {
			$id = $model->insert( array_merge( $data, OrderTiming::initialTiming() ) );
		} catch ( \Throwable $exception ) {
			$cleanup();
			// The unique import key also protects simultaneous webhook retries.
			$existing = ( new OrderModel() )->where( 'import_key', $data['import_key'] )->first();
			if ( $existing ) {
				return $this->duplicate( $existing, $data );
			}
			throw new RuntimeException( 'The order could not be saved.', 500, $exception );
		}
		if ( $id === false ) {
			$cleanup();
			$existing = ( new OrderModel() )->where( 'import_key', $data['import_key'] )->first();
			if ( $existing ) { return $this->duplicate( $existing, $data ); }
			throw new RuntimeException( 'The order could not be saved.', 500 );
		}
		return array( 'ok' => true, 'duplicate' => false, 'order_id' => (int) $id, 'order_number' => $data['order_number'] );
	}

	private function duplicate( array $existing, array $data ): array {
		if ( ! hash_equals( (string) $existing['import_payload_hash'], $data['import_payload_hash'] ) ) {
			throw new InvalidArgumentException( 'This source and external_id already exist with different data. Edit the existing order in FlowPress.', 409 );
		}
		return array( 'ok' => true, 'duplicate' => true, 'order_id' => (int) $existing['id'], 'order_number' => $existing['order_number'] );
	}

	private function sizesForItem( string $type, string $category ): array {
		if ( $type === 'Hat' ) { return array( 'One Size Fits All', 'S/M', 'L/XL' ); }
		if ( ! in_array( $type, array( 'Shirt', 'Polo', 'Hoodie', 'Jacket', 'Long Sleeve Shirt', 'Crew Neck Sweater', 'Vest', 'Sash', 'Apron', 'Beanie' ), true ) ) { return array( 'Units' ); }
		return match ( $category ) {
			'Women' => array( 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL' ),
			'Youth' => array( 'YXS', 'YS', 'YM', 'YL', 'YXL' ),
			'Toddler' => array( '1T', '2T', '3T', '4T', '5T', '6T' ),
			default => array( 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL' ),
		};
	}

	private function keys( \stdClass $object, array $allowed, string $path ): void {
		$unknown = array_diff( array_keys( (array) $object ), $allowed );
		if ( $unknown ) { throw new InvalidArgumentException( $path . ': unknown field(s): ' . implode( ', ', $unknown ), 422 ); }
	}

	private function text( \stdClass $object, string $field, int $max, bool $required = false ): string {
		$value = $object->$field ?? '';
		if ( ! is_string( $value ) || mb_strlen( $value ) > $max || str_contains( $value, "\0" ) || ( $required && trim( $value ) === '' ) ) {
			throw new InvalidArgumentException( $field . ' must be ' . ( $required ? 'a nonempty' : 'a' ) . ' string of at most ' . $max . ' characters.', 422 );
		}
		return trim( $value );
	}

	private function money( \stdClass $object, string $field, string $path ): int {
		$value = $object->$field ?? '0';
		if ( ! ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) || ! preg_match( '/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $value ) ) {
			throw new InvalidArgumentException( $path . '.' . $field . ' must be a nonnegative amount with at most two decimal places.', 422 );
		}
		return (int) round( (float) $value * 100 );
	}

	private function decimal( int $cents ): string { return number_format( $cents / 100, 2, '.', '' ); }

	private function canonical( mixed $value ): mixed {
		if ( $value instanceof \stdClass ) {
			$values = (array) $value;
			ksort( $values );
			return (object) array_map( fn( $item ) => $this->canonical( $item ), $values );
		}
		return is_array( $value ) ? array_map( fn( $item ) => $this->canonical( $item ), $value ) : $value;
	}
}
