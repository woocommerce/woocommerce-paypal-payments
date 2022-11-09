<?php
/**
 * The Token object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class Token
 */
class Token {

	/**
	 * The Token data.
	 *
	 * @var object
	 */
	private $json;

	/**
	 * The timestamp when the Token was created.
	 *
	 * @var int
	 */
	private $created;

	/**
	 * Token constructor.
	 *
	 * @param object $json The JSON object.
	 * @throws RuntimeException When The JSON object is not valid.
	 */
	public function __construct( $json ) {
		if ( ! isset( $json->created ) ) {
			$json->created = time();
		}
		if ( ! $this->validate( $json ) ) {
			throw new RuntimeException( 'Token not valid' );
		}
		$this->json = $json;
	}

	/**
	 * Returns the timestamp when the Token is expired.
	 *
	 * @return int
	 */
	public function expiration_timestamp(): int {

		return $this->json->created + $this->json->expires_in;
	}

	/**
	 * Returns the token.
	 *
	 * @return string
	 */
	public function token(): string {
		return (string) $this->json->token;
	}

	/**
	 * Returns whether the Token is still valid.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return time() < $this->json->created + $this->json->expires_in;
	}

	/**
	 * Returns the Token as JSON string.
	 *
	 * @return string
	 */
	public function as_json(): string {
		return wp_json_encode( $this->json );
	}

	/**
	 * Returns a Token based off a JSON string.
	 *
	 * @param string $json The JSON string.
	 *
	 * @return static
	 */
	public static function from_json( string $json ): self {
		$json = (object) json_decode( $json );
		if ( isset( $json->access_token ) || isset( $json->client_token ) ) {
			$json->token = isset( $json->access_token ) ? $json->access_token : $json->client_token;
		}

		return new Token( $json );
	}

	/**
	 * Checks if vaulting is available in access token scope.
	 *
	 * @return bool Whether vaulting features are enabled or not.
	 */
	public function vaulting_available(): bool {
		if ( ! isset( $this->json->scope ) ) {
			return false;
		}

		if ( strpos(
			$this->json->scope,
			'https://uri.paypal.com/services/vault/payment-tokens/readwrite'
		) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Validates whether a JSON object can be transformed to a Token object.
	 *
	 * @param object $json The JSON object.
	 *
	 * @return bool
	 */
	private function validate( $json ): bool {
		$property_map = array(
			'created'    => 'is_int',
			'expires_in' => 'is_int',
			'token'      => 'is_string',
		);

		foreach ( $property_map as $property => $validator ) {
			if ( ! isset( $json->{$property} ) || ! $validator( $json->{$property} ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if tracking is available in access token scope.
	 *
	 * @return bool Whether tracking features are enabled or not.
	 */
	public function is_tracking_available(): bool {
		if ( ! isset( $this->json->scope ) ) {
			return false;
		}

		if ( strpos(
			$this->json->scope,
			'https://uri.paypal.com/services/shipping/trackers/readwrite'
		) !== false ) {
			return true;
		}

		return false;
	}
}
