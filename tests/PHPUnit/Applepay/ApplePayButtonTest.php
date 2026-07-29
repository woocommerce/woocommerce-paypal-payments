<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Applepay;

use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayButton;
use WooCommerce\PayPalCommerce\Applepay\Assets\DataToAppleButtonScripts;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;
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
}
