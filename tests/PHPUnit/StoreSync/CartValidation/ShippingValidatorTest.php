<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
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
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

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
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

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
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

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
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

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
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );
		$this->assertInstanceOf( ValidationIssue::class, $result[1] );
		$this->assertInstanceOf( ValidationIssue::class, $result[2] );
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
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'PO Box delivery not available', $issue_data['message'] );
		$this->assertStringContainsString( 'signature confirmation', $issue_data['user_message'] );
		$this->assertSame( 'shipping_address', $issue_data['field'] );

		// Verify context (context is a list of IssueContext::to_array() results)
		$this->assertArrayHasKey( 'context', $issue_data );
		$this->assertCount( 1, $issue_data['context'] );
		$context = $issue_data['context'][0];
		$this->assertArrayHasKey( 'restricted_items', $context );
		$this->assertArrayHasKey( 'restriction_reason', $context );
		$this->assertArrayHasKey( 'po_box_detected', $context );
		$this->assertSame( 'signature_required', $context['restriction_reason'] );
		$this->assertTrue( $context['po_box_detected'] );

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
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );
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

	/**
	 * Given a cart containing a shippable product and no shipping address
	 * When validate() is called
	 * Then a ValidationIssue for the 'shipping_address' field is returned
	 * And the issue asks the customer to provide a shipping address
	 * And a PROVIDE_MISSING_FIELD resolution option is included
	 */
	public function test_validate_returns_missing_address_issue_when_cart_needs_shipping(): void {
		$product = \Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'needs_shipping' )->andReturn( true );
		$this->product_manager->shouldReceive( 'find_product' )
			->andReturn( $product );

		$cart = PayPalCart::from_array(
			array(
				'items'          => array(
					array(
						'item_id'  => '1',
						'quantity' => 1,
						'name'     => 'Shippable Product',
					),
				),
				'payment_method' => 'paypal',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'Shipping address is required', $issue_data['message'] );
		$this->assertStringContainsString( 'Please provide a shipping address', $issue_data['user_message'] );
		$this->assertSame( 'shipping_address', $issue_data['field'] );

		$this->assertArrayHasKey( 'resolution_options', $issue_data );
		$actions = array_column( $issue_data['resolution_options'], 'action' );
		$this->assertContains( 'PROVIDE_MISSING_FIELD', $actions );
	}

	/**
	 * Given a cart shipping to a US address with a postal code containing invalid characters
	 * When validate() is called
	 * Then a ValidationIssue for 'shipping_address.postal_code' is returned
	 * And the issue message mentions an invalid postal code format
	 */
	public function test_validate_detects_invalid_postal_code_format(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array( 'US' => 'United States' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'US',
				'address_line_1' => '123 Main St',
				'admin_area_2'   => 'New York',
				'postal_code'    => '100!01',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'Invalid postal code format', $issue_data['message'] );
		$this->assertSame( 'shipping_address.postal_code', $issue_data['field'] );
	}

	// Gap A3: Fallback from empty shipping countries to allowed countries

	/**
	 * Given WooCommerce has no shipping-specific countries configured (empty list)
	 * And the allowed-countries list contains US
	 * And the cart ships to US
	 * When validate() is called
	 * Then no country issue is returned (US is permitted via the allowed-countries fallback)
	 */
	public function test_validate_falls_back_to_allowed_countries_when_shipping_countries_is_empty(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States' ),
			array() // empty shipping countries → fallback to allowed countries
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

	// Group B: PayPal-level US-only restriction (failing tests — feature not yet implemented)

	/**
	 * Given PayPal's supported-country allowlist only includes the United States
	 * And WooCommerce also allows US
	 * And the cart ships to US
	 * When validate() is called
	 * Then no PayPal-restriction issue is returned
	 */
	public function test_validate_accepts_us_shipping_address_for_paypal_restriction(): void {
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

	/**
	 * Given PayPal's supported-country allowlist only includes the United States
	 * And WooCommerce allows both US and CA
	 * And the cart ships to CA (Canada)
	 * When validate() is called
	 * Then a ValidationIssue is returned indicating CA is not supported by PayPal
	 * And the issue targets 'shipping_address.country_code'
	 * And the user message mentions that only specific countries are currently supported
	 */
	public function test_validate_rejects_canada_shipping_address_due_to_paypal_restriction(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States', 'CA' => 'Canada' ),
			array( 'US' => 'United States', 'CA' => 'Canada' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'CA',
				'address_line_1' => '456 Maple Ave',
				'admin_area_2'   => 'Toronto',
				'postal_code'    => 'M5V 3A8',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertSame( 'shipping_address.country_code', $issue_data['field'] );

		$message_lower = strtolower( $issue_data['message'] );
		$this->assertTrue(
			strpos( $message_lower, 'ca' ) !== false
				|| strpos( $message_lower, 'paypal' ) !== false
				|| strpos( $message_lower, 'not supported' ) !== false,
			'Expected issue message to reference CA, PayPal, or "not supported", got: ' . $issue_data['message']
		);

		$user_message_lower = strtolower( $issue_data['user_message'] );
		$this->assertTrue(
			strpos( $user_message_lower, 'united states' ) !== false
				|| strpos( $user_message_lower, 'supported' ) !== false
				|| strpos( $user_message_lower, 'country' ) !== false,
			'Expected user_message to mention supported countries, got: ' . $issue_data['user_message']
		);
	}

	/**
	 * Given PayPal's supported-country allowlist only includes the United States
	 * And WooCommerce allows both US and DE
	 * And the cart ships to DE (Germany)
	 * When validate() is called
	 * Then a ValidationIssue is returned indicating DE is not supported by PayPal
	 * And the issue targets 'shipping_address.country_code'
	 */
	public function test_validate_rejects_germany_shipping_address_due_to_paypal_restriction(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States', 'DE' => 'Germany' ),
			array( 'US' => 'United States', 'DE' => 'Germany' )
		);

		$cart = $this->create_cart_with_shipping(
			array(
				'country_code'   => 'DE',
				'address_line_1' => '1 Unter den Linden',
				'admin_area_2'   => 'Berlin',
				'postal_code'    => '10117',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertSame( 'shipping_address.country_code', $issue_data['field'] );

		$message_lower = strtolower( $issue_data['message'] );
		$this->assertTrue(
			strpos( $message_lower, 'de' ) !== false
				|| strpos( $message_lower, 'paypal' ) !== false
				|| strpos( $message_lower, 'not supported' ) !== false,
			'Expected issue message to reference DE, PayPal, or "not supported", got: ' . $issue_data['message']
		);
	}

	/**
	 * Given a country is disallowed by both WooCommerce settings and the PayPal country restriction
	 * And the cart ships to FR (France), which WooCommerce does not allow and PayPal does not support
	 * When validate() is called
	 * Then exactly one country-level ValidationIssue is returned (checks do not stack)
	 */
	public function test_validate_produces_single_country_issue_when_disallowed_by_both_woocommerce_and_paypal(): void {
		$this->mock_wc_countries(
			array( 'US' => 'United States', 'FR' => 'France' ),
			array( 'US' => 'United States' ) // FR not in shipping countries
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

		$country_issues = array_filter(
			$result,
			static function ( ValidationIssue $issue ): bool {
				$data = $issue->to_array();
				return isset( $data['field'] ) && $data['field'] === 'shipping_address.country_code';
			}
		);

		$this->assertCount( 1, $country_issues );
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
