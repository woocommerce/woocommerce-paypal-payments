<?php
/**
 * The message repository.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Repository
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AdminNotices\Repository;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\MutableMessage;

/**
 * Class Repository
 */
class Repository implements RepositoryInterface {

	const NOTICES_FILTER           = 'ppcp.admin-notices.current-notices';
	const PERSISTED_NOTICES_OPTION = 'woocommerce_ppcp-admin-notices';

	/**
	 * Returns an unfiltered list of all Message instances that were registered
	 * in the current request.
	 *
	 * @return Message[]
	 */
	protected function get_all_messages() : array {
		return array_filter(
			/**
			 * Returns the list of admin messages.
			 */
			(array) apply_filters(
				self::NOTICES_FILTER,
				array()
			),
			function ( $element ) : bool {
				return is_a( $element, Message::class );
			}
		);
	}

	/**
	 * Returns current messages to display, which excludes muted messages.
	 *
	 * @return Message[]
	 */
	public function current_message() : array {
		return array_filter(
			$this->get_all_messages(),
			function ( Message $element ) : bool {
				if ( $element instanceof MutableMessage ) {
					return ! $element->is_muted();
				}

				return true;
			}
		);
	}

	/**
	 * Finds messages with a given nag_id. As the nag_id should be unique, this
	 * method should return an array containing 0 or 1 Message instance.
	 *
	 * All messages that can be muted must be registered in `wp_doing_ajax()`
	 * requests, otherwise the Ajax endpoint cannot mute them!
	 *
	 * @param string $nag_id Defines the message to retrieve.
	 *
	 * @return MutableMessage[]
	 */
	public function get_by_nag_id( string $nag_id ) : array {
		$nag_id = sanitize_title( $nag_id );
		if ( ! $nag_id ) {
			return array();
		}

		return array_filter(
			$this->get_all_messages(),
			function ( Message $element ) use ( $nag_id ) : bool {
				return $element instanceof MutableMessage && $nag_id === $element->nag_id();
			}
		);
	}

	/**
	 * Adds a message to persist between page reloads.
	 *
	 * @param Message $message The message.
	 *
	 * @return void
	 */
	public function persist( Message $message ) : void {
		$persisted_notices = get_option( self::PERSISTED_NOTICES_OPTION ) ?: array();

		$persisted_notices[] = $message->to_array();

		update_option( self::PERSISTED_NOTICES_OPTION, $persisted_notices );
	}

	/**
	 * Adds a message to persist between page reloads.
	 *
	 * @return array|Message[]
	 */
	public function get_persisted_and_clear() : array {
		$notices = array();

		$persisted_data = get_option( self::PERSISTED_NOTICES_OPTION ) ?: array();
		foreach ( $persisted_data as $notice_data ) {
			if ( is_array( $notice_data ) ) {
				$notices[] = Message::from_array( $notice_data );
			}
		}

		update_option( self::PERSISTED_NOTICES_OPTION, array(), true );

		return $notices;
	}
}
