<?php
/**
 * The message repository.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices\Repository;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;

/**
 * Class Repository
 */
class Repository implements RepositoryInterface {

	const NOTICES_FILTER = 'ppcp.admin-notices.current-notices';

	/**
	 * Returns the current messages.
	 *
	 * @return Message[]
	 */
	public function current_message(): array {
		return array_filter(
			/**
			 * Returns the list of admin messages.
			 */
			(array) apply_filters(
				self::NOTICES_FILTER,
				array()
			),
			function( $element ) : bool {
				return is_a( $element, Message::class );
			}
		);
	}
}
