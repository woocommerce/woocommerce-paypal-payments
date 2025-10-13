<?php
/**
 * Defines the gift option schema.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @see GiftOptionsTest - Unit tests for this class.
 */
class GiftOptions extends AgenticSchema {
	private bool $is_gift = false;

	private bool $gift_wrap = false;

	private ?string $sender_name = null;

	private ?string $gift_message = null;

	private ?string $delivery_date = null;

	private ?array $recipient = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->is_gift       = false;
		$this->gift_wrap     = false;
		$this->sender_name   = null;
		$this->gift_message  = null;
		$this->delivery_date = null;
		$this->recipient     = null;

		// Optional fields.
		if ( isset( $input['is_gift'] ) ) {
			$this->is_gift = $input['is_gift'];
		}
		if ( isset( $input['gift_wrap'] ) ) {
			$this->gift_wrap = $input['gift_wrap'];
		}
		if ( ! empty( $input['sender_name'] ) ) {
			$this->sender_name = $input['sender_name'];
		}
		if ( ! empty( $input['gift_message'] ) ) {
			$this->gift_message = $input['gift_message'];
		}
		if ( ! empty( $input['delivery_date'] ) ) {
			$this->delivery_date = $input['delivery_date'];
		}
		if ( ! empty( $input['recipient'] ) ) {
			$this->recipient = $input['recipient'];
		}
	}

	public function is_gift(): bool {
		return $this->is_gift;
	}

	public function gift_wrap(): bool {
		return $this->gift_wrap;
	}

	public function sender_name(): ?string {
		return $this->sender_name;
	}

	public function gift_message(): ?string {
		return $this->gift_message;
	}

	/**
	 * @return string|null The scheduled delivery date, in RFC3339 format, or null.
	 */
	public function delivery_date(): ?string {
		return $this->delivery_date;
	}

	/**
	 * @return null|array Recipient as simple array, no own schema.
	 */
	public function recipient(): ?array {
		return $this->recipient;
	}
}
