<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers GiftOptions
 */
class GiftOptionsTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return GiftOptions::class;
	}

	protected function get_valid_data(): array {
		return array();
	}

	/**
	 * Tests that GiftOptions stores and returns the is_gift flag.
	 */
	public function test_is_gift_accessor(): void {
		$data    = array( 'is_gift' => true );
		$options = GiftOptions::from_array( $data );

		$this->assertTrue( $options->is_gift() );
	}

	/**
	 * Tests that is_gift returns false when not provided.
	 */
	public function test_is_gift_returns_false_when_missing(): void {
		$data    = array();
		$options = GiftOptions::from_array( $data );

		$this->assertFalse( $options->is_gift() );
	}

	/**
	 * Tests that GiftOptions stores and returns the gift_wrap flag.
	 */
	public function test_gift_wrap_accessor(): void {
		$data    = array( 'gift_wrap' => true );
		$options = GiftOptions::from_array( $data );

		$this->assertTrue( $options->gift_wrap() );
	}

	/**
	 * Tests that gift_wrap returns false when not provided.
	 */
	public function test_gift_wrap_returns_false_when_missing(): void {
		$data    = array();
		$options = GiftOptions::from_array( $data );

		$this->assertFalse( $options->gift_wrap() );
	}

	/**
	 * Tests that GiftOptions stores and returns the sender_name.
	 */
	public function test_sender_name_accessor(): void {
		$data    = array( 'sender_name' => 'John Smith' );
		$options = GiftOptions::from_array( $data );

		$this->assertSame( 'John Smith', $options->sender_name() );
	}

	/**
	 * Tests that sender_name returns null when not provided.
	 */
	public function test_sender_name_returns_null_when_missing(): void {
		$data    = array();
		$options = GiftOptions::from_array( $data );

		$this->assertNull( $options->sender_name() );
	}

	/**
	 * Tests that GiftOptions stores and returns the gift_message.
	 */
	public function test_gift_message_accessor(): void {
		$data    = array( 'gift_message' => 'Happy Birthday! Hope you enjoy this gift.' );
		$options = GiftOptions::from_array( $data );

		$this->assertSame( 'Happy Birthday! Hope you enjoy this gift.', $options->gift_message() );
	}
}
