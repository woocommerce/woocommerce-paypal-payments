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
	 * Payments constructor.
	 *
	 * @param Authorization ...$authorizations The Authorizations.
	 */
	public function __construct( Authorization ...$authorizations ) {
		$this->authorizations = $authorizations;
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
}
