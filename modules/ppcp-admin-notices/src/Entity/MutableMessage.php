<?php
/**
 * Extends the Message class to permanently dismiss notices for single users.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Entity
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AdminNotices\Entity;

/**
 * Class Message
 */
class MutableMessage extends Message {

	/**
	 * Prefix for DB keys to store IDs of permanently muted notices.
	 */
	public const USER_META_PREFIX = '_ppcp_notice_';

	/**
	 * An internal ID to permanently dismiss the nag message.
	 *
	 * @var string
	 */
	private $nag_id;

	/**
	 * Message constructor.
	 *
	 * @param string $message     The message text.
	 * @param string $type        The message type.
	 * @param bool   $dismissible Whether the message is dismissible.
	 * @param string $wrapper     The wrapper selector that will contain the notice.
	 * @param string $nag_id      ID of a nag message that can be permanently muted by the user.
	 */
	public function __construct( string $message, string $type, bool $dismissible = true, string $wrapper = '', string $nag_id = '' ) {
		parent::__construct( $message, $type, $dismissible, $wrapper );

		$this->nag_id = sanitize_key( $nag_id );
	}

	/**
	 * Whether the message can be permanently muted by the user.
	 *
	 * @return bool
	 */
	public function is_mutable() : bool {
		return $this->is_dismissible() && $this->nag_id;
	}

	/**
	 * Returns the sanitized nag-ID that identifies a permanently dismissible message.
	 *
	 * @param bool $with_db_prefix Whether to add the user-meta prefix.
	 *
	 * @return string
	 */
	public function nag_id( bool $with_db_prefix = false ) : string {
		if ( ! $this->nag_id ) {
			return '';
		}

		return $with_db_prefix ? self::USER_META_PREFIX . $this->nag_id : $this->nag_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_array() : array {
		$data           = parent::to_array();
		$data['nag_id'] = $this->nag_id;

		return $data;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return MutableMessage
	 */
	public static function from_array( array $data ) : Message {
		return new MutableMessage(
			(string) ( $data['message'] ?? '' ),
			(string) ( $data['type'] ?? '' ),
			(bool) ( $data['dismissible'] ?? true ),
			(string) ( $data['wrapper'] ?? '' ),
			(string) ( $data['nag_id'] ?? '' )
		);
	}

	/**
	 * Whether the message was permanently muted by the current user.
	 *
	 * @return bool
	 */
	public function is_muted() : bool {
		$user_id = get_current_user_id();

		if ( ! $this->nag_id || ! $user_id ) {
			return false;
		}

		return 0 < (int) get_user_meta( $user_id, $this->nag_id( true ), true );
	}

	/**
	 * Mark the message as permanently muted by the current user.
	 *
	 * @return void
	 */
	public function mute() : void {
		$user_id = get_current_user_id();

		if ( $this->nag_id && $user_id && ! $this->is_muted() ) {
			update_user_meta( $user_id, $this->nag_id( true ), time() );
		}
	}
}
