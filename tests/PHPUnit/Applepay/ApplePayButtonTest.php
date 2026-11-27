<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Applepay;

use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayButton;
use WooCommerce\PayPalCommerce\Applepay\Assets\DataToAppleButtonScripts;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayButton
 */
class ApplePayButtonTest extends TestCase {
	private $settings;
	private $context;
	private ApplePayButton $sut;

	public function setUp(): void {
		parent::setUp();

		$this->settings = Mockery::mock( SettingsProvider::class );
		$this->context  = Mockery::mock( Context::class );

		$this->sut = new ApplePayButton(
			$this->settings,
			Mockery::mock( PaymentSettings::class ),
			Mockery::mock( LoggerInterface::class ),
			Mockery::mock( OrderProcessor::class ),
			Mockery::mock( AssetGetter::class ),
			'1.0.0',
			Mockery::mock( DataToAppleButtonScripts::class ),
			Mockery::mock( CartProductsHelper::class ),
			$this->context
		);
	}

	public function testDisabledWhenApplePayGloballyDisabled(): void {
		$this->settings->shouldReceive( 'applepay_enabled' )->andReturn( false );

		$this->assertFalse( $this->sut->is_enabled() );
	}

	/**
	 * @scenario Regression test for PCP-4084. Same master-switch bug as Google Pay: disabling
	 *           "Enable payment methods in this location" for Classic Checkout must hide Apple
	 *           Pay even though it's still selected in that location's method list.
	 */
	public function testDisabledWhenLocationDisabledEvenIfMethodSelected(): void {
		$this->settings->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->context->shouldReceive( 'context' )->andReturn( 'checkout' );
		$this->settings->shouldReceive( 'button_styling' )->with( 'checkout' )->andReturn(
			new LocationStylingDTO( 'classic_checkout', false, array( ApplePayGateway::ID ) )
		);

		$this->assertFalse( $this->sut->is_enabled() );
	}

	public function testEnabledWhenLocationEnabledAndMethodSelected(): void {
		$this->settings->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->context->shouldReceive( 'context' )->andReturn( 'checkout' );
		$this->settings->shouldReceive( 'button_styling' )->with( 'checkout' )->andReturn(
			new LocationStylingDTO( 'classic_checkout', true, array( ApplePayGateway::ID ) )
		);

		$this->assertTrue( $this->sut->is_enabled() );
	}

	public function testDisabledWhenLocationEnabledButMethodNotSelected(): void {
		$this->settings->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->context->shouldReceive( 'context' )->andReturn( 'checkout' );
		$this->settings->shouldReceive( 'button_styling' )->with( 'checkout' )->andReturn(
			new LocationStylingDTO( 'classic_checkout', true, array() )
		);

		$this->assertFalse( $this->sut->is_enabled() );
	}

	/**
	 * Invokes a protected method of the SUT.
	 *
	 * customer_address() and getShippingPackages() are not part of the public API, but they
	 * are the exact seams where the buyer's state is dropped during the live shipping-rate
	 * step. Driving the fully public update_shipping_contact() path would require stubbing
	 * nonce verification, WC_Countries and response-template output unrelated to this bug,
	 * so reflection is used instead.
	 */
	private function invoke_protected( string $method, array $args ) {
		$reflection = new ReflectionMethod( $this->sut, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $this->sut, $args );
	}

	/**
	 * GIVEN an Apple Pay shipping address carrying state "NY"
	 * WHEN customer_address() applies that address to the WooCommerce customer during the
	 *      live shipping-rate calculation step
	 * THEN the customer's shipping state is set to "NY"
	 */
	public function testCustomerAddressAppliesStateToWooCommerceCustomer(): void {
		when( 'wc_get_base_location' )->justReturn( array( 'country' => 'US' ) );

		$customer = Mockery::mock()->shouldIgnoreMissing();
		$customer->shouldReceive( 'set_shipping_state' )->once()->with( 'NY' );

		when( 'WC' )->justReturn( (object) array( 'customer' => $customer ) );

		$this->invoke_protected(
			'customer_address',
			array(
				array(
					'country'  => 'US',
					'postcode' => '10001',
					'city'     => 'New York',
					'state'    => 'NY',
				),
			)
		);

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a customer address carrying state "NY"
	 * WHEN getShippingPackages() builds the shipping destination used to calculate rates
	 * THEN the destination's state is "NY" instead of being forced to an empty string
	 *
	 * Regression test: state-scoped shipping zones return no rates because the destination
	 * state was previously hardcoded to '', even when the address carried a state.
	 */
	public function testGetShippingPackagesSetsDestinationState(): void {
		$cart    = (object) array( 'cart_contents' => array() );
		$session = (object) array( 'applied_coupon' => array() );

		when( 'WC' )->justReturn(
			(object) array(
				'cart'    => $cart,
				'session' => $session,
			)
		);
		when( 'apply_filters' )->returnArg( 2 );

		$packages = $this->invoke_protected(
			'getShippingPackages',
			array(
				array(
					'country'  => 'US',
					'postcode' => '10001',
					'city'     => 'New York',
					'state'    => 'NY',
				),
				100.0,
			)
		);

		$this->assertSame( 'NY', $packages[0]['destination']['state'] );
	}

	/**
	 * Builds a button whose cart already holds one item and whose product cannot be added.
	 *
	 * @param array $error_notices The WooCommerce error notices present after the failed add.
	 */
	private function button_failing_to_add_product( array $error_notices, Mockery\MockInterface $cart ): ApplePayButton {
		$cart->allows( 'is_empty' )->andReturn( false );
		$cart->allows( 'get_cart_contents' )->andReturn( array( 'old-key' => array( 'product_id' => 5 ) ) );
		$cart->allows( 'remove_cart_item' );

		when( 'WC' )->justReturn( (object) array( 'cart' => $cart ) );
		when( 'wc_get_notices' )->justReturn( $error_notices );
		when( 'wp_strip_all_tags' )->alias( 'strip_tags' );

		$cart_products = Mockery::mock( CartProductsHelper::class );
		$cart_products->allows( 'set_cart' );
		$cart_products->allows( 'product_from_data' )->andReturn( array( 'product' => 'unused' ) );
		$cart_products->allows( 'add_products' )->andThrow( new \Exception( 'Error adding products to cart.' ) );

		return new ApplePayButton(
			$this->settings,
			Mockery::mock( PaymentSettings::class ),
			Mockery::mock( LoggerInterface::class ),
			Mockery::mock( OrderProcessor::class ),
			Mockery::mock( AssetGetter::class ),
			'1.0.0',
			Mockery::mock( DataToAppleButtonScripts::class ),
			$cart_products,
			$this->context
		);
	}

	private function request_data(): Assets\ApplePayDataObjectHttp {
		$request = Mockery::mock( Assets\ApplePayDataObjectHttp::class );
		$request->allows( 'product_id' )->andReturn( '10' );
		$request->allows( 'product_quantity' )->andReturn( '1' );
		$request->allows( 'product_variations' )->andReturn( array() );
		$request->allows( 'product_extra' )->andReturn( array() );
		$request->allows( 'product_booking' )->andReturn( array() );

		return $request;
	}

	private function error_thrown_by_prepare_cart( ApplePayButton $button ): \Exception {
		try {
			$button->prepare_cart( $this->request_data() );
		} catch ( \Exception $exception ) {
			return $exception;
		}

		$this->fail( 'prepare_cart() should have thrown.' );
	}

	/**
	 * GIVEN a shopper's cart that was set aside and a product the add-to-cart validation refuses
	 * WHEN the Apple Pay cart is prepared
	 * THEN the shopper's saved cart items are restored
	 */
	public function testPrepareCartRestoresSavedCartWhenProductIsRefused(): void {
		$cart = Mockery::mock( \WC_Cart::class );
		$cart->expects( 'restore_cart_item' )->once()->with( 'old-key' );
		when( 'wc_clear_notices' )->justReturn( null );

		$this->error_thrown_by_prepare_cart( $this->button_failing_to_add_product( array(), $cart ) );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a product the add-to-cart validation refuses with error notices
	 * WHEN the Apple Pay cart is prepared
	 * THEN the notices are cleared so they do not show again on the next page view
	 */
	public function testPrepareCartClearsNoticesWhenProductIsRefused(): void {
		$cart = Mockery::mock( \WC_Cart::class )->shouldIgnoreMissing();
		\Brain\Monkey\Functions\expect( 'wc_clear_notices' )->once();

		$this->error_thrown_by_prepare_cart(
			$this->button_failing_to_add_product( array( array( 'notice' => 'Max 5 per order.' ) ), $cart )
		);

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a product the add-to-cart validation refuses with two error notices, one containing markup
	 * WHEN the Apple Pay cart is prepared
	 * THEN the thrown message is the notice text without tags, joined by a space
	 */
	public function testPrepareCartThrowsNoticeTextAsMessage(): void {
		$cart = Mockery::mock( \WC_Cart::class )->shouldIgnoreMissing();
		when( 'wc_clear_notices' )->justReturn( null );

		$exception = $this->error_thrown_by_prepare_cart(
			$this->button_failing_to_add_product(
				array(
					array( 'notice' => '<strong>Max 5</strong> per order.' ),
					array( 'notice' => 'Try again later.' ),
				),
				$cart
			)
		);

		$this->assertSame( 'Max 5 per order. Try again later.', $exception->getMessage() );
	}

	/**
	 * GIVEN a product that cannot be added and no error notices
	 * WHEN the Apple Pay cart is prepared
	 * THEN the thrown message is the original failure message
	 */
	public function testPrepareCartFallsBackToOriginalMessageWithoutNotices(): void {
		$cart = Mockery::mock( \WC_Cart::class )->shouldIgnoreMissing();
		when( 'wc_clear_notices' )->justReturn( null );

		$exception = $this->error_thrown_by_prepare_cart( $this->button_failing_to_add_product( array(), $cart ) );

		$this->assertSame( 'Error adding products to cart.', $exception->getMessage() );
	}
}
