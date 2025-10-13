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

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->is_gift     = false;
		$this->gift_wrap   = false;
		$this->sender_name = null;

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
		return 'Happy Birthday! Hope you enjoy this gift.';
	}
}
