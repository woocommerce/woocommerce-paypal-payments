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
	 * The messagte text.
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
	 * @param string $message The message text.
	 * @param string $type The message type.
	 * @param bool   $dismissable Whether the message is dismissable.
	 * @param string $wrapper The wrapper selector that will contain the notice.
	 */
	public function __construct( string $message, string $type, bool $dismissable = true, string $wrapper = '' ) {
		$this->type        = $type;
		$this->message     = $message;
		$this->dismissable = $dismissable;
		$this->wrapper     = $wrapper;
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
	 * Returns the wrapper selector that will contain the notice.
	 *
	 * @return string
	 */
	public function wrapper(): string {
		return $this->wrapper;
	}
}
