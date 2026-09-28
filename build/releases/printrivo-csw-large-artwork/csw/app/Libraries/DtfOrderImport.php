<?php
namespace App\Libraries;

use InvalidArgumentException;

/** DTF builder payload, flattened into the existing garment/placement editor formats. */
final class DtfOrderImport {
	public const MAX_BYTES = 16777216;
	public const MAX_ARTWORK_BYTES = 500 * 1024 * 1024;
	public const MAX_PIXELS = 256000000;

	public function parse( string $json, array $uploads = array() ): array {
		if ( strlen( $json ) > self::MAX_BYTES ) { throw new InvalidArgumentException( 'DTF JSON must be 16 MB or smaller.', 413 ); }
		try { $payload = json_decode( $json, false, 32, JSON_THROW_ON_ERROR ); }
		catch ( \JsonException $error ) { throw new InvalidArgumentException( 'Invalid JSON: ' . $error->getMessage(), 400 ); }
		if ( ! $payload instanceof \stdClass || ! is_array( $payload->designs ?? null ) || ! $payload->designs || count( $payload->designs ) > 50 ) {
			throw new InvalidArgumentException( 'DTF orders need a designs array with 1 to 50 designs.', 422 );
		}
		$original = $payload;
		$flat = clone $payload;
		unset( $flat->designs );
		if ( isset( $flat->items ) ) { throw new InvalidArgumentException( 'Use designs[].garments instead of items in DTF orders.', 422 ); }
		$flat->items = array();
		$designs = $garmentMeta = $placements = $artworkData = array();
		$garmentIds = $designIds = array();
		foreach ( $payload->designs as $design ) {
			$this->keys( $design, array( 'id', 'name', 'artwork', 'garments', 'placements' ), 'design' );
			$id = $this->id( $design->id ?? null, 'Design ID' );
			if ( isset( $designIds[$id] ) ) { throw new InvalidArgumentException( 'Design IDs must be unique.', 422 ); }
			$designIds[$id] = true;
			if ( ! is_string( $design->name ?? null ) || trim( $design->name ) === '' || mb_strlen( $design->name ) > 160 ) { throw new InvalidArgumentException( 'Design name is required (maximum 160 characters).', 422 ); }
			$artwork = $design->artwork ?? array();
			if ( ! is_array( $artwork ) || count( $artwork ) > 25 ) { throw new InvalidArgumentException( 'artwork must be an array of up to 25 images per design.', 422 ); }
			$files = array();
			foreach ( $artwork as $image ) {
				$this->keys( $image, array( 'id', 'name', 'data_base64', 'upload_field', 'sha256' ), 'artwork' );
				$imageId = $this->id( $image->id ?? null, 'Artwork ID' );
				if ( isset( $files[$imageId] ) ) { throw new InvalidArgumentException( 'Artwork IDs must be unique within a design.', 422 ); }
				if ( ! is_string( $image->name ?? null ) || trim( $image->name ) === '' || mb_strlen( $image->name ) > 160 ) { throw new InvalidArgumentException( 'Artwork requires a name of at most 160 characters.', 422 ); }
				if ( isset( $image->upload_field ) ) {
					if ( isset( $image->data_base64 ) || ! is_string( $image->upload_field ) || ! isset( $uploads[$image->upload_field] ) || ! is_string( $image->sha256 ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $image->sha256 ) ) {
						throw new InvalidArgumentException( 'Artwork requires an uploaded file and its SHA-256 digest, without data_base64.', 422 );
					}
					// Paths come only from validated PHP uploads, never from JSON fields or URLs.
					$temp = $uploads[$image->upload_field];
					if ( ! is_file( $temp ) || filesize( $temp ) > self::MAX_ARTWORK_BYTES ) { throw new InvalidArgumentException( 'Each artwork file must be 500 MB or smaller.', 413 ); }
					$digest = hash_file( 'sha256', $temp );
					if ( ! hash_equals( $image->sha256, $digest ) ) { throw new InvalidArgumentException( 'Uploaded artwork did not match its SHA-256 digest.', 422 ); }
					$info = @getimagesize( $temp );
					$storedHash = hash( 'sha256', $id . ':' . $imageId . ':' . $digest );
					$storage = array( 'temp_path' => $temp );
				} else {
					if ( isset( $image->sha256 ) || ! is_string( $image->data_base64 ?? null ) ) { throw new InvalidArgumentException( 'Artwork requires data_base64 or an uploaded file.', 422 ); }
					$bytes = base64_decode( $image->data_base64, true );
					if ( $bytes === false || strlen( $bytes ) > 8388608 ) { throw new InvalidArgumentException( 'Inline artwork must be valid base64 and at most 8 MB. Use a multipart upload for larger files.', 422 ); }
					$info = @getimagesizefromstring( $bytes );
					$storedHash = hash( 'sha256', $id . ':' . $imageId . ':' . $bytes );
					$storage = array( 'body' => $bytes );
				}
				$extensions = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp' );
				if ( ! $info || ! isset( $extensions[$info['mime']] ) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS ) { throw new InvalidArgumentException( 'Artwork must be a PNG, JPEG or WebP image of at most 256 megapixels.', 422 ); }
				$name = 'dtf-' . substr( $storedHash, 0, 32 ) . '.' . $extensions[$info['mime']];
				$files[$imageId] = $name;
				$artworkData[] = array( 'stored_name' => $name, 'original_name' => trim( $image->name ) ) + $storage;
				if ( count( $artworkData ) > 250 ) { throw new InvalidArgumentException( 'An order supports up to 250 artwork files.', 422 ); }
			}
			if ( ! is_array( $design->garments ?? null ) || ! $design->garments || count( $design->garments ) > 100 ) { throw new InvalidArgumentException( 'Each design needs 1 to 100 garments.', 422 ); }
			$designGarments = array();
			foreach ( $design->garments as $garment ) {
				$this->keys( $garment, array( 'id', 'item_type', 'brand', 'color', 'category', 'source', 'production', 'sizes', 'item_price', 'print_price' ), 'garment' );
				$garmentId = $this->id( $garment->id ?? null, 'Garment ID' );
				if ( isset( $garmentIds[$garmentId] ) ) { throw new InvalidArgumentException( 'Garment IDs must be unique across the order.', 422 ); }
				$garmentIds[$garmentId] = true;
				$brand = $garment->brand ?? '';
				if ( ! is_string( $brand ) || mb_strlen( $brand ) > 80 ) { throw new InvalidArgumentException( 'Garment brand must be a string of at most 80 characters.', 422 ); }
				$source = $garment->source ?? 'csw_house';
				if ( ! in_array( $source, array( 'customer', 'csw_house' ), true ) ) { throw new InvalidArgumentException( 'Garment source must be customer or csw_house.', 422 ); }
				$item = clone $garment; unset( $item->id, $item->brand, $item->source );
				$flat->items[] = $item;
				$garmentMeta[] = array( 'design_id' => $id, 'garment_id' => $garmentId, 'brand' => $brand, 'item_source' => $source );
				$designGarments[$garmentId] = $garment;
			}
			$designPlacements = $design->placements ?? array();
			if ( ! is_array( $designPlacements ) || count( $designPlacements ) > 100 ) { throw new InvalidArgumentException( 'placements must be an array of up to 100 placements per design.', 422 ); }
			$placementIds = array();
			foreach ( $designPlacements as $placement ) {
				$this->keys( $placement, array( 'id', 'name', 'garment_id', 'side', 'size', 'artwork_id', 'x', 'y', 'width', 'height', 'rotation' ), 'placement' );
				$placementId = $this->id( $placement->id ?? null, 'Placement ID' );
				if ( isset( $placementIds[$placementId] ) ) { throw new InvalidArgumentException( 'Placement IDs must be unique within a design.', 422 ); }
				$placementIds[$placementId] = true;
				$garmentId = $this->id( $placement->garment_id ?? null, 'Placement garment_id' );
				$artId = $this->id( $placement->artwork_id ?? null, 'Placement artwork_id' );
				if ( ! isset( $designGarments[$garmentId], $files[$artId] ) ) { throw new InvalidArgumentException( 'Each placement must reference a garment and artwork in the same design.', 422 ); }
				$name = $placement->name ?? '';
				if ( ! is_string( $name ) || trim( $name ) === '' || mb_strlen( $name ) > 160 ) { throw new InvalidArgumentException( 'Each placement needs a name of at most 160 characters.', 422 ); }
				$side = $placement->side ?? 'front';
				if ( ! in_array( $side, array( 'front', 'back', 'left side', 'right side' ), true ) ) { throw new InvalidArgumentException( 'Placement side must be front, back, left side or right side.', 422 ); }
				$size = $placement->size ?? '';
				if ( ! is_string( $size ) || ( $size !== '' && ! isset( $designGarments[$garmentId]->sizes->$size ) ) ) { throw new InvalidArgumentException( 'Placement size must be empty or a size on its garment.', 422 ); }
				$geometry = array();
				foreach ( array( 'x' => 35, 'y' => 25, 'width' => 20, 'height' => 20, 'rotation' => 0 ) as $key => $default ) {
					$value = $placement->$key ?? $default;
					if ( ! ( is_int( $value ) || is_float( $value ) ) || ! is_finite( (float) $value ) || $value < 0 || $value > ( $key === 'rotation' ? 360 : 100 ) ) { throw new InvalidArgumentException( 'Placement coordinates must be numbers from 0 to 100; rotation from 0 to 360.', 422 ); }
					$geometry[$key] = $value;
				}
				if ( $geometry['width'] <= 0 || $geometry['height'] <= 0 || $geometry['x'] + $geometry['width'] > 100 || $geometry['y'] + $geometry['height'] > 100 ) { throw new InvalidArgumentException( 'Placement dimensions must be positive and fit within the canvas.', 422 ); }
				$placements[] = array( 'id' => 'dtf-' . substr( hash( 'sha256', $id . "\0" . $placementId ), 0, 32 ), 'design_id' => $id, 'garment_id' => $garmentId, 'name' => $name, 'side' => $side, 'size' => $size,
					'type' => strtolower( str_replace( ' ', '-', is_string( $designGarments[$garmentId]->item_type ?? null ) ? $designGarments[$garmentId]->item_type : 'shirt' ) ),
					'placements' => array( array( 'file' => $files[$artId], 'x' => $geometry['x'], 'y' => $geometry['y'], 'w' => $geometry['width'], 'h' => $geometry['height'], 'rotation' => $geometry['rotation'] ) ) );
			}
			$designs[] = array( 'id' => $id, 'name' => $design->name, 'artwork' => array_values( $files ) );
		}
		$data = ( new JsonOrderImport() )->normalize( $flat );
		$garments = json_decode( $data['color_size_quantities'], true );
		foreach ( $garments as $i => &$garment ) { $garment = array_merge( $garment, $garmentMeta[$i] ); } unset( $garment );
		try { $structure = OrderDesigns::link( $designs, $garments, $placements, '', true ); }
		catch ( InvalidArgumentException $error ) { throw new InvalidArgumentException( $error->getMessage(), 422 ); }
		$data['order_designs'] = json_encode( $structure['designs'], JSON_THROW_ON_ERROR );
		$data['color_size_quantities'] = json_encode( $structure['garments'], JSON_THROW_ON_ERROR );
		$data['print_placements'] = json_encode( $structure['placements'], JSON_THROW_ON_ERROR );
		$data['_import_artwork'] = $artworkData;
		$data['import_payload_hash'] = hash( 'sha256', json_encode( $this->canonical( $original ), JSON_THROW_ON_ERROR ) );
		return $data;
	}
	private function id( mixed $value, string $label ): string {
		if ( ! is_string( $value ) || ! preg_match( '/^[A-Za-z0-9_-]{1,60}$/D', $value ) ) { throw new InvalidArgumentException( $label . ' must be 1 to 60 letters, numbers, hyphens or underscores.', 422 ); }
		return $value;
	}
	private function keys( mixed $value, array $allowed, string $label ): void {
		if ( ! $value instanceof \stdClass || array_diff( array_keys( (array) $value ), $allowed ) ) { throw new InvalidArgumentException( $label . ' must be an object with only supported fields.', 422 ); }
	}
	private function canonical( mixed $value ): mixed {
		if ( $value instanceof \stdClass ) { $values = (array) $value; ksort( $values ); return (object) array_map( fn( $item ) => $this->canonical( $item ), $values ); }
		return is_array( $value ) ? array_map( fn( $item ) => $this->canonical( $item ), $value ) : $value;
	}
}
