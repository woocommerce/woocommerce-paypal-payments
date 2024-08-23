<?php
/**
 * The message entity.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices\Entity;

/**
 * Class Message
 */
class Message {

	/**
	 * Prefix for DB keys to store IDs of permanently muted notices.
	 */
	private const USER_META_PREFIX = '_ppcp_notice_';

	/**
	 * An internal ID to permanently dismiss the nag message.
	 *
	 * @var string
	 */
	private $nag_id;

	/**
	 * The message text.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * The message type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Whether the message is dismissable.
	 *
	 * @var bool
	 */
	private $dismissable;

	/**
	 * The wrapper selector that will contain the notice.
	 *
	 * @var string
	 */
	private $wrapper;

	/**
	 * Message constructor.
	 *
	 * @param string $message     The message text.
	 * @param string $type        The message type.
	 * @param bool   $dismissable Whether the message is dismissable.
	 * @param string $wrapper     The wrapper selector that will contain the notice.
	 * @param string $nag_id      ID of a nag message that can be permanently muted by the user.
	 */
	public function __construct( string $message, string $type, bool $dismissable = true, string $wrapper = '', string $nag_id = '' ) {
		$this->type        = $type;
		$this->message     = $message;
		$this->dismissable = $dismissable;
		$this->wrapper     = $wrapper;
		$this->nag_id      = sanitize_key( $nag_id );
	}

	/**
	 * Returns the message text.
	 *
	 * @return string
	 */
	public function message(): string {
		return $this->message;
	}

	/**
	 * Returns the message type.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Returns whether the message is dismissable.
	 *
	 * @return bool
	 */
	public function is_dismissable(): bool {
		return $this->dismissable;
	}

	/**
	 * Whether the message can be permanently muted by the user.
	 *
	 * @return bool
	 */
	public function is_mutable() : bool {
		return $this->dismissable && $this->nag_id;
	}

	/**
	 * Returns the wrapper selector that will contain the notice.
	 *
	 * @return string
	 */
	public function wrapper(): string {
		return $this->wrapper;
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
	 * Returns the object as array, for serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'type'        => $this->type,
			'message'     => $this->message,
			'dismissable' => $this->dismissable,
			'wrapper'     => $this->wrapper,
			'nag_id'      => $this->nag_id,
		);
	}

	/**
	 * Converts a plain array to a full Message instance, during deserialization.
	 *
	 * @param array $data Data generated by `Message::to_array()`.
	 *
	 * @return Message
	 */
	public static function from_array( array $data ) : Message {
		return new Message(
			(string) ( $data['message'] ?? '' ),
			(string) ( $data['type'] ?? '' ),
			(bool) ( $data['dismissable'] ?? true ),
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
