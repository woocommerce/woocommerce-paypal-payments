<?php
/**
 * Trait which helps to remove the prefix of IDs.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

/**
 * Trait PrefixTrait
 */
trait PrefixTrait {


	/**
	 * The prefix.
	 *
	 * @var string
	 */
	private $prefix = '';

	/**
	 * Removes the prefix from a given Id.
	 *
	 * @param string $custom_id The custom id.
	 *
	 * @return int
	 */
	private function sanitize_custom_id( string $custom_id ): int {

		$id = $custom_id;
		if ( strlen( $this->prefix ) > 0 && 0 === strpos( $id, $this->prefix ) ) {
			$id = substr( $id, strlen( $this->prefix ) );
		}
		return (int) $id;
	}
}
