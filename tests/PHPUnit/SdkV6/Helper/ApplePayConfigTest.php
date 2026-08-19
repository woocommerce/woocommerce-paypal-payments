<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class ApplePayConfigTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @var SettingsProvider&Mockery\MockInterface
	 */
	private $provider;

	public function setUp(): void {
		parent::setUp();

		// Satisfy should_render()'s wp_loaded guard; the guard test overrides this.
		when( 'did_action' )->justReturn( 1 );

		$this->provider = Mockery::mock( SettingsProvider::class );
	}

	private function configFor( ?SubscriptionHelper $subscription_helper = null, ?callable $is_available = null ): ApplePayConfig {
		return new ApplePayConfig(
			$this->provider,
			$subscription_helper ?? Mockery::mock( SubscriptionHelper::class ),
			$is_available ?? $this->failIfCalled()
		);
	}

	private function subscriptions( bool $on_product, bool $in_cart, bool $at_payorder = false ): SubscriptionHelper {
		$helper = Mockery::mock( SubscriptionHelper::class );
		$helper->shouldReceive( 'locations_with_subscription_product' )->andReturn(
			array(
				'product'  => $on_product,
				'payorder' => $at_payorder,
				'cart'     => $in_cart,
			)
		);

		return $helper;
	}

	private function buttonLanguage( string $language = 'en-US' ): void {
		$this->provider->shouldReceive( 'applepay_button_language' )->andReturn( $language );
	}

	private function noSubscriptions(): SubscriptionHelper {
		return $this->subscriptions( false, false );
	}

	private function subscriptionInCart(): SubscriptionHelper {
		return $this->subscriptions( false, true );
	}

	private function subscriptionOnProductPage(): SubscriptionHelper {
		return $this->subscriptions( true, false );
	}

	/**
	 * A renewal, which only `payorder` flags: `cart` excludes renewals.
	 */
	private function subscriptionRenewal(): SubscriptionHelper {
		return $this->subscriptions( false, false, true );
	}

	private function available(): callable {
		return static fn(): bool => true;
	}

	private function notAvailable(): callable {
		return static fn(): bool => false;
	}

	private function failIfCalled(): callable {
		return function (): bool {
			$this->fail( 'The availability callable must not be invoked.' );

			return false;
		};
	}

	private function styling(
		string $location,
		bool $enabled,
		array $methods,
		string $shape,
		string $label,
		string $color
	): LocationStylingDTO {
		return new LocationStylingDTO( $location, $enabled, $methods, $shape, $label, $color );
	}

	private function stylingEnabled( string $location = 'checkout' ): LocationStylingDTO {
		return $this->styling( $location, true, array( ApplePayGateway::ID ), 'rect', 'pay', 'black' );
	}

	private function stylingDisabled(): LocationStylingDTO {
		return $this->styling( 'checkout', false, array( ApplePayGateway::ID ), 'rect', 'pay', 'black' );
	}

	private function stylingWithoutApplePayMethod(): LocationStylingDTO {
		return $this->styling( 'checkout', true, array( 'venmo' ), 'rect', 'pay', 'black' );
	}

	private function stylingWithColor( string $color ): LocationStylingDTO {
		return $this->styling( 'checkout', true, array(), 'rect', 'pay', $color );
	}

	private function stylingWithLabel( string $label ): LocationStylingDTO {
		return $this->styling( 'checkout', true, array(), 'rect', $label, 'black' );
	}

	private function stylingWithShape( string $shape ): LocationStylingDTO {
		return $this->styling( 'checkout', true, array(), $shape, 'pay', 'black' );
	}

	/**
	 * GIVEN the Apple Pay gateway is disabled globally
	 * WHEN checking whether it should render in a context
	 * THEN it is reported as not rendering, regardless of the location's own styling
	 */
	public function testNotRenderedWhenGatewayIsOffGlobally(): void {
		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( false );

		$config = $this->configFor( $this->noSubscriptions(), $this->available() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the gateway is on globally but the location's styling marks it disabled
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenLocationStylingIsDisabled(): void {
		$styling = $this->stylingDisabled();

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->noSubscriptions(), $this->available() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the gateway and location are enabled but Apple Pay is not among the
	 * location's active payment methods
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenGatewayIdMissingFromMethods(): void {
		$styling = $this->stylingWithoutApplePayMethod();

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->noSubscriptions(), $this->available() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN every gate that controls rendering is satisfied
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as rendering
	 */
	public function testRenderedWhenNothingBlocksIt(): void {
		$styling = $this->stylingEnabled();

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->noSubscriptions(), $this->available() );

		$this->assertTrue( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the settings fully enable Apple Pay for the location but the merchant
	 * cannot currently offer Apple Pay
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenMerchantIsNotAvailable(): void {
		$styling = $this->stylingEnabled();

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->noSubscriptions(), $this->notAvailable() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the settings fully enable Apple Pay and the merchant is available, but
	 * there is a subscription product in the cart
	 * WHEN checking whether it should render in the cart, checkout, or mini-cart
	 * THEN it is reported as not rendering, because Apple Pay has no vaulting
	 *
	 * @dataProvider cartBackedContextProvider
	 */
	public function testNotRenderedWhenSubscriptionIsInCart( string $context ): void {
		$styling = $this->stylingEnabled( $context );

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( $context )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionInCart(), $this->available() );

		$this->assertFalse( $config->should_render( $context ) );
	}

	public function cartBackedContextProvider(): array {
		return array(
			'cart context'      => array( 'cart' ),
			'checkout context'  => array( 'checkout' ),
			'mini-cart context' => array( 'mini-cart' ),
		);
	}

	/**
	 * GIVEN the settings fully enable Apple Pay and the merchant is available, but
	 * the product page shows a subscription product
	 * WHEN checking whether it should render on the product page
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenSubscriptionIsOnProductPage(): void {
		$styling = $this->stylingEnabled( 'product' );

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'product' )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionOnProductPage(), $this->available() );

		$this->assertFalse( $config->should_render( 'product' ) );
	}

	/**
	 * GIVEN the settings fully enable Apple Pay and the merchant is available, but
	 * the order being paid is a subscription renewal
	 * WHEN checking whether it should render on the pay-for-order page
	 * THEN it is reported as not rendering, because Apple Pay has no vaulting
	 */
	public function testNotRenderedWhenPayOrderIsARenewal(): void {
		$styling = $this->stylingEnabled( 'pay-now' );

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'pay-now' )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionRenewal(), $this->available() );

		$this->assertFalse( $config->should_render( 'pay-now' ) );
	}

	/**
	 * GIVEN the settings fully enable Apple Pay and the merchant is available, but
	 * the cart being checked out is a subscription renewal
	 * WHEN checking whether it should render at classic checkout
	 * THEN it is reported as not rendering, because Apple Pay has no vaulting
	 */
	public function testNotRenderedWhenCheckoutHoldsARenewal(): void {
		$styling = $this->stylingEnabled( 'checkout' );

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionRenewal(), $this->available() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN a subscription product is in the cart but the product page itself does
	 * not show a subscription product
	 * WHEN checking whether it should render on the product page
	 * THEN it is reported as rendering
	 */
	public function testSubscriptionInCartDoesNotBlockProductPage(): void {
		$styling = $this->stylingEnabled( 'product' );

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'product' )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionInCart(), $this->available() );

		$this->assertTrue( $config->should_render( 'product' ) );
	}

	/**
	 * GIVEN a subscription product is shown on the product page but the cart itself
	 * does not contain a subscription product
	 * WHEN checking whether it should render at checkout
	 * THEN it is reported as rendering
	 */
	public function testSubscriptionOnProductPageDoesNotBlockCheckout(): void {
		$styling = $this->stylingEnabled();

		$this->provider->shouldReceive( 'applepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionOnProductPage(), $this->available() );

		$this->assertTrue( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN a merchant-availability callable
	 * WHEN the config object is constructed and should_render() is not called
	 * THEN the callable is never invoked
	 */
	public function testConstructionDoesNotInvokeAvailabilityCallable(): void {
		$this->configFor( $this->noSubscriptions(), $this->failIfCalled() );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN wp_loaded has not run yet
	 * AND settings, availability and subscription state would otherwise allow rendering
	 * WHEN checking whether Apple Pay should render
	 * THEN it is reported as not rendering
	 * AND the merchant availability callable is never invoked
	 */
	public function testNotRenderedBeforeWpLoadedHasRun(): void {
		when( 'did_action' )->justReturn( 0 );
		when( '_doing_it_wrong' )->justReturn( null );

		$config = $this->configFor( $this->noSubscriptions(), $this->failIfCalled() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN a location styling color from the admin settings
	 * WHEN building the Apple Pay button styles for a context
	 * THEN the color is normalized to a value the Apple Pay button API accepts
	 *
	 * @dataProvider colorProvider
	 */
	public function testNormalizesColor( string $inputColor, string $expectedColor ): void {
		$styling = $this->stylingWithColor( $inputColor );

		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedColor, $styles['color'] );
	}

	public function colorProvider(): array {
		return array(
			'silver maps to white'                  => array( 'silver', 'white' ),
			'gold maps to black'                    => array( 'gold', 'black' ),
			'blue maps to black'                    => array( 'blue', 'black' ),
			'already valid white-outline is stable' => array( 'white-outline', 'white-outline' ),
		);
	}

	/**
	 * GIVEN a location styling label from the admin settings
	 * WHEN building the Apple Pay button styles for a context
	 * THEN the label is normalized to a button type the Apple Pay button API accepts
	 *
	 * @dataProvider typeProvider
	 */
	public function testNormalizesType( string $inputLabel, string $expectedType ): void {
		$styling = $this->stylingWithLabel( $inputLabel );

		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedType, $styles['type'] );
	}

	public function typeProvider(): array {
		return array(
			'checkout maps to check-out'  => array( 'checkout', 'check-out' ),
			'buynow maps to buy'          => array( 'buynow', 'buy' ),
			'paypal maps to plain'        => array( 'paypal', 'plain' ),
			'already valid pay is stable' => array( 'pay', 'pay' ),
		);
	}

	/**
	 * GIVEN a configured button language from the admin settings
	 * WHEN building the Apple Pay button styles for a context
	 * THEN the language is normalized to a locale the Apple Pay button API accepts
	 *
	 * @dataProvider languageProvider
	 */
	public function testNormalizesLanguage( string $inputLanguage, string $expectedLanguage ): void {
		$styling = $this->stylingEnabled();

		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage( $inputLanguage );

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedLanguage, $styles['language'] );
	}

	public function languageProvider(): array {
		return array(
			'underscore locale is converted to hyphenated locale' => array( 'en_US', 'en-US' ),
			'already hyphenated locale is stable'                 => array( 'en-GB', 'en-GB' ),
			'unsupported code becomes empty'                      => array( 'xx', '' ),
		);
	}

	/**
	 * GIVEN a location styling shape from the admin settings
	 * WHEN building the Apple Pay button styles for a context
	 * THEN the shape is converted to the CSS length border radius the
	 * <apple-pay-button> custom property expects, not a bare integer
	 *
	 * @dataProvider shapeProvider
	 */
	public function testConvertsShapeToCssLengthBorderRadius( string $inputShape, string $expectedRadius ): void {
		$styling = $this->stylingWithShape( $inputShape );

		$this->provider->shouldReceive( 'applepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedRadius, $styles['borderRadius'] );
		$this->assertIsString( $styles['borderRadius'] );
	}

	public function shapeProvider(): array {
		return array(
			'pill shape'                          => array( 'pill', '24px' ),
			'rect shape'                          => array( 'rect', '4px' ),
			'unknown shape falls back to default' => array( 'hexagon', '24px' ),
			'empty shape falls back to default'   => array( '', '24px' ),
		);
	}

	/**
	 * GIVEN the site's blog name
	 * WHEN asking for the name to show on the payment sheet
	 * THEN the blog name is returned, because it labels the sheet's total and
	 * identifies the merchant during validation
	 */
	public function testDisplayNameReturnsBlogName(): void {
		when( 'get_bloginfo' )->justReturn( 'My Test Shop' );

		$this->assertSame( 'My Test Shop', $this->configFor()->display_name() );
	}
}
