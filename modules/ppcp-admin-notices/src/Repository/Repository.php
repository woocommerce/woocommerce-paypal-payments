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

	const NOTICES_FILTER           = 'ppcp.admin-notices.current-notices';
	const PERSISTED_NOTICES_OPTION = 'woocommerce_ppcp-admin-notices';

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

	/**
	 * Adds a message to persist between page reloads.
	 *
	 * @param Message $message The message.
	 * @return void
	 */
	public function persist( Message $message ): void {
		$persisted_notices = get_option( self::PERSISTED_NOTICES_OPTION ) ?: array();

		$persisted_notices[] = $message->to_array();

		update_option( self::PERSISTED_NOTICES_OPTION, $persisted_notices );
	}

	/**
	 * Adds a message to persist between page reloads.
	 *
	 * @return array|Message[]
	 */
	public function get_persisted_and_clear(): array {
		$notices = array();

		$persisted_data = get_option( self::PERSISTED_NOTICES_OPTION ) ?: array();
		foreach ( $persisted_data as $notice_data ) {
			$notices[] = new Message(
				(string) ( $notice_data['message'] ?? '' ),
				(string) ( $notice_data['type'] ?? '' ),
				(bool) ( $notice_data['dismissable'] ?? true ),
				(string) ( $notice_data['wrapper'] ?? '' )
			);
		}

		update_option( self::PERSISTED_NOTICES_OPTION, array(), true );
		return $notices;
	}
}
