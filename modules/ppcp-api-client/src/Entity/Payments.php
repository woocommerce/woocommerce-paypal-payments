<?php
/**
 * The Payments object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Payments
 */
class Payments {

	/**
	 * The Authorizations.
	 *
	 * @var Authorization[]
	 */
	private $authorizations;

	/**
	 * The Captures.
	 *
	 * @var Capture[]
	 */
	private $captures;

	/**
	 * Payments constructor.
	 *
	 * @param array $authorizations The Authorizations.
	 * @param array $captures The Captures.
	 */
	public function __construct( array $authorizations, array $captures ) {
		foreach ( $authorizations as $key => $authorization ) {
			if ( is_a( $authorization, Authorization::class ) ) {
				continue;
			}
			unset( $authorizations[ $key ] );
		}
		foreach ( $captures as $key => $capture ) {
			if ( is_a( $capture, Capture::class ) ) {
				continue;
			}
			unset( $captures[ $key ] );
		}
		$this->authorizations = $authorizations;
		$this->captures       = $captures;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'authorizations' => array_map(
				static function ( Authorization $authorization ): array {
					return $authorization->to_array();
				},
				$this->authorizations()
			),
			'captures'       => array_map(
				static function ( Capture $capture ): array {
					return $capture->to_array();
				},
				$this->captures()
			),
		);
	}

	/**
	 * Returns the Authoriatzions.
	 *
	 * @return Authorization[]
	 **/
	public function authorizations(): array {
		return $this->authorizations;
	}

	/**
	 * Returns the Captures.
	 *
	 * @return Capture[]
	 **/
	public function captures(): array {
		return $this->captures;
	}
}
