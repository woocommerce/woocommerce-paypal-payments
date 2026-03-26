<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidAddress;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ShippingUnavailable;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Filters\expectApplied;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ShippingValidator
 */
class ShippingValidatorTest extends TestCase {

	private ShippingValidator $validator;
	private $product_manager;

	public function setUp(): void {
		parent::setUp();
		$this->product_manager = \Mockery::mock( ProductManager::class );
		$this->validator       = new ShippingValidator( $this->product_manager );
	}

	public function test_validate_returns_null_for_allowed_country(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => '123 Main St',
				'admin_area_2'   => 'New York',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertNull( $result );
	}

	public function test_validate_detects_disallowed_country(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States', 'FR' => 'France' ),
			array( 'US' => 'United States' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'FR',
				'address_line_1' => '123 Rue de la Paix',
				'admin_area_2'   => 'Paris',
				'postal_code'    => '75001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ShippingUnavailable::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'Shipping to FR is not available', $issue_data['message'] );
		$this->assertStringContainsString( 'We do not ship to France', $issue_data['user_message'] );
		$this->assertSame( 'shipping_address.country_code', $issue_data['field'] );
	}

	public function test_validate_returns_null_for_cart_without_shipping_address(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$product = \Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'needs_shipping' )->andReturn( false );
		$this->product_manager->shouldReceive( 'find_product' )
			->andReturn( $product );

		$cart = PayPalCart::from_array(
			array(
				'items'          => array(
					array(
						'item_id'  => '1',
						'quantity' => 1,
						'name'     => 'Test Product',
					),
				),
				'payment_method' => 'paypal',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertNull( $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_validate_passes_country_check_when_wc_not_available(): void {
		// WC() must not be defined; run in a separate process so that
		// Patchwork stubs from other tests do not leak in.

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'FR',
				'address_line_1' => '123 Rue de la Paix',
				'admin_area_2'   => 'Paris',
				'postal_code'    => '75001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertNull( $result );
	}

	// Scenario 1: Invalid Shipping Address Tests

	public function test_validate_detects_missing_street_address(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'admin_area_2'   => 'New York',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( InvalidAddress::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'missing street address', $issue_data['message'] );
		$this->assertSame( 'shipping_address.address_line_1', $issue_data['field'] );
	}

	public function test_validate_detects_missing_city(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => '123 Main St',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( InvalidAddress::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'missing city', $issue_data['message'] );
		$this->assertSame( 'shipping_address.admin_area_2', $issue_data['field'] );
	}

	public function test_validate_detects_missing_postal_code(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => '123 Main St',
				'admin_area_2'   => 'New York',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( InvalidAddress::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'missing postal code', $issue_data['message'] );
		$this->assertSame( 'shipping_address.postal_code', $issue_data['field'] );
	}

	public function test_validate_detects_multiple_missing_fields(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code' => 'US',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertInstanceOf( InvalidAddress::class, $result[0] );
		$this->assertInstanceOf( InvalidAddress::class, $result[1] );
		$this->assertInstanceOf( InvalidAddress::class, $result[2] );
	}

	// Scenario 2: PO Box Restriction Tests

	public function test_validate_detects_po_box_with_signature_required_items(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$product = \Mockery::mock( 'WC_Product' );
		$this->product_manager->shouldReceive( 'find_product' )
			->andReturn( $product );

		expectApplied( 'woocommerce_paypal_payments_store_sync_item_requires_signature' )
			->once()
			->andReturn( true );

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => 'PO Box 123',
				'admin_area_2'   => 'New York',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ShippingUnavailable::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'PO Box delivery not available', $issue_data['message'] );
		$this->assertStringContainsString( 'signature confirmation', $issue_data['user_message'] );
		$this->assertSame( 'shipping_address', $issue_data['field'] );

		// Verify context
		$this->assertArrayHasKey( 'context', $issue_data );
		$this->assertArrayHasKey( 'restricted_items', $issue_data['context'] );
		$this->assertArrayHasKey( 'restriction_reason', $issue_data['context'] );
		$this->assertArrayHasKey( 'po_box_detected', $issue_data['context'] );
		$this->assertSame( 'signature_required', $issue_data['context']['restriction_reason'] );
		$this->assertTrue( $issue_data['context']['po_box_detected'] );

		// Verify resolution_options
		$this->assertArrayHasKey( 'resolution_options', $issue_data );
		$this->assertCount( 2, $issue_data['resolution_options'] );
		$this->assertSame( 'UPDATE_ADDRESS', $issue_data['resolution_options'][0]['action'] );
		$this->assertSame( 'REMOVE_ITEM', $issue_data['resolution_options'][1]['action'] );
	}

	public function test_validate_accepts_po_box_without_signature_required_items(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$product = \Mockery::mock( 'WC_Product' );
		$this->product_manager->shouldReceive( 'find_product' )
			->andReturn( $product );

		expectApplied( 'woocommerce_paypal_payments_store_sync_item_requires_signature' )
			->once()
			->andReturn( false );

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => 'PO Box 123',
				'admin_area_2'   => 'New York',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertNull( $result );
	}

	public function test_validate_accepts_street_address_with_signature_required_items(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		// No need to mock product or filter because street addresses don't trigger signature check
		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => '123 Main St',
				'admin_area_2'   => 'New York',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		// Street addresses are always valid for signature-required items
		$this->assertNull( $result );
	}

	public function test_validate_detects_po_box_with_dots_and_spaces(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$product = \Mockery::mock( 'WC_Product' );
		$this->product_manager->shouldReceive( 'find_product' )
			->andReturn( $product );

		expectApplied( 'woocommerce_paypal_payments_store_sync_item_requires_signature' )
			->once()
			->andReturn( true );

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => 'P.O. Box 456',
				'admin_area_2'   => 'New York',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ShippingUnavailable::class, $result[0] );
	}

	public function test_validate_handles_product_not_found_for_signature_check(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$this->product_manager->shouldReceive( 'find_product' )
			->andReturn( null );

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => 'PO Box 123',
				'admin_area_2'   => 'New York',
				'postal_code'    => '10001',
			)
		);

		$result = $this->validator->validate( $cart );

		// Should pass because product not found means no signature requirement
		$this->assertNull( $result );
	}

	private function create_cart_with_shipping( array $address_data ): PayPalCart {
		return PayPalCart::from_array(
			array(
				'items'            => array(
					array(
						'item_id'  => '1',
						'quantity' => 1,
						'name'     => 'Test Product',
					),
				),
				'shipping_address' => $address_data,
				'payment_method'   => 'paypal',
			)
		);
	}

	private function mock_wc_countries( array $all_countries, array $shipping_countries ): void {
		$countries_mock = \Mockery::mock( 'WC_Countries' );
		$countries_mock->allows( 'get_countries' )->andReturn( $all_countries );
		$countries_mock->allows( 'get_allowed_countries' )->andReturn( $all_countries );
		$countries_mock->allows( 'get_shipping_countries' )->andReturn( $shipping_countries );

		when( 'WC' )->alias(
			function () use ( $countries_mock ) {
				$wc            = new \stdClass();
				$wc->countries = $countries_mock;

				return $wc;
			}
		);
	}
}
