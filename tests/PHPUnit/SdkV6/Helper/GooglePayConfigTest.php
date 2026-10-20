<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class GooglePayConfigTest extends TestCase {
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

	private function configFor( ?SubscriptionHelper $subscription_helper = null, ?callable $is_available = null ): GooglePayConfig {
		return new GooglePayConfig(
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

	private function buttonLanguage( string $language = 'en' ): void {
		$this->provider->shouldReceive( 'googlepay_button_language' )->andReturn( $language );
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
		return $this->styling( $location, true, array( GooglePayGateway::ID ), 'rect', 'pay', 'black' );
	}

	private function stylingDisabled(): LocationStylingDTO {
		return $this->styling( 'checkout', false, array( GooglePayGateway::ID ), 'rect', 'pay', 'black' );
	}

	private function stylingWithoutGooglePayMethod(): LocationStylingDTO {
		return $this->styling( 'checkout', true, array( 'venmo' ), 'rect', 'pay', 'black' );
	}

	private function stylingWithColor( string $color ): LocationStylingDTO {
		return $this->styling( 'checkout', true, array(), 'rect', 'pay', $color );
	}

	private function stylingWithLabel( string $label, string $location = 'checkout' ): LocationStylingDTO {
		return $this->styling( $location, true, array(), 'rect', $label, 'black' );
	}

	private function stylingWithShape( string $shape ): LocationStylingDTO {
		return $this->styling( 'checkout', true, array(), $shape, 'pay', 'black' );
	}

	/**
	 * GIVEN the Google Pay gateway is disabled globally
	 * WHEN checking whether it should render in a context
	 * THEN it is reported as not rendering, regardless of the location's own styling
	 */
	public function testNotRenderedWhenGatewayIsOffGlobally(): void {
		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( false );

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

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->noSubscriptions(), $this->available() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the gateway and location are enabled but Google Pay is not among the
	 * location's active payment methods
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenGatewayIdMissingFromMethods(): void {
		$styling = $this->stylingWithoutGooglePayMethod();

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

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

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->noSubscriptions(), $this->available() );

		$this->assertTrue( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the settings fully enable Google Pay for the location but the merchant
	 * cannot currently offer Google Pay
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenMerchantIsNotAvailable(): void {
		$styling = $this->stylingEnabled();

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$config = $this->configFor( $this->noSubscriptions(), $this->notAvailable() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the settings fully enable Google Pay and the merchant is available, but
	 * there is a subscription product in the cart
	 * WHEN checking whether it should render in the cart, checkout, or mini-cart
	 * THEN it is reported as not rendering
	 *
	 * @dataProvider cartBackedContextProvider
	 */
	public function testNotRenderedWhenSubscriptionIsInCart( string $context ): void {
		$styling = $this->stylingEnabled( $context );

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( $context )->andReturn( $styling );

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
	 * GIVEN the settings fully enable Google Pay and the merchant is available, but
	 * the product page shows a subscription product
	 * WHEN checking whether it should render on the product page
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenSubscriptionIsOnProductPage(): void {
		$styling = $this->stylingEnabled( 'product' );

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'product' )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionOnProductPage(), $this->available() );

		$this->assertFalse( $config->should_render( 'product' ) );
	}

	/**
	 * GIVEN the settings fully enable Google Pay and the merchant is available, but
	 * the order being paid is a subscription renewal
	 * WHEN checking whether it should render on the pay-for-order page
	 * THEN it is reported as not rendering, because Google Pay has no vaulting
	 */
	public function testNotRenderedWhenPayOrderIsARenewal(): void {
		$styling = $this->stylingEnabled( 'pay-now' );

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'pay-now' )->andReturn( $styling );

		$config = $this->configFor( $this->subscriptionRenewal(), $this->available() );

		$this->assertFalse( $config->should_render( 'pay-now' ) );
	}

	/**
	 * GIVEN the settings fully enable Google Pay and the merchant is available, but
	 * the cart being checked out is a subscription renewal
	 * WHEN checking whether it should render at classic checkout
	 * THEN it is reported as not rendering, because Google Pay has no vaulting
	 */
	public function testNotRenderedWhenCheckoutHoldsARenewal(): void {
		$styling = $this->stylingEnabled( 'checkout' );

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

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

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'product' )->andReturn( $styling );

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

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

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
	 * WHEN checking whether Google Pay should render
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
	 * WHEN building the Google Pay button styles for a context
	 * THEN the color is normalized to a value the Google button API accepts
	 *
	 * @dataProvider colorProvider
	 */
	public function testNormalizesColor( string $inputColor, string $expectedColor ): void {
		$styling = $this->stylingWithColor( $inputColor );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedColor, $styles['color'] );
	}

	public function colorProvider(): array {
		return array(
			'silver maps to white'          => array( 'silver', 'white' ),
			'gold maps to black'            => array( 'gold', 'black' ),
			'blue maps to black'            => array( 'blue', 'black' ),
			'already valid black is stable' => array( 'black', 'black' ),
		);
	}

	/**
	 * GIVEN a location styling label from the admin settings
	 * WHEN building the Google Pay button styles for a context
	 * THEN the label is normalized to a button type the Google button API accepts
	 *
	 * @dataProvider typeProvider
	 */
	public function testNormalizesType( string $inputLabel, string $expectedType ): void {
		$styling = $this->stylingWithLabel( $inputLabel );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedType, $styles['type'] );
	}

	public function typeProvider(): array {
		return array(
			'buynow maps to buy'          => array( 'buynow', 'buy' ),
			'paypal maps to plain'        => array( 'paypal', 'plain' ),
			'already valid pay is stable' => array( 'pay', 'pay' ),
		);
	}

	/**
	 * GIVEN a configured button language from the admin settings
	 * WHEN building the Google Pay button styles for a context
	 * THEN the language is normalized to a two-letter code the Google button API accepts
	 *
	 * @dataProvider languageProvider
	 */
	public function testNormalizesLanguage( string $inputLanguage, string $expectedLanguage ): void {
		$styling = $this->stylingEnabled();

		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage( $inputLanguage );

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedLanguage, $styles['language'] );
	}

	public function languageProvider(): array {
		return array(
			'locale with underscore is truncated to language code' => array( 'en_US', 'en' ),
			'locale with hyphen is truncated to language code'     => array( 'en-GB', 'en' ),
			'unsupported code becomes empty'                       => array( 'xx', '' ),
		);
	}

	/**
	 * GIVEN a location styling shape from the admin settings
	 * WHEN building the Google Pay button styles for a context
	 * THEN the shape is converted to the numeric border radius the Google button API expects
	 *
	 * @dataProvider shapeProvider
	 */
	public function testConvertsShapeToNumericBorderRadius( string $inputShape, int $expectedRadius ): void {
		$styling = $this->stylingWithShape( $inputShape );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( 'checkout' );

		$this->assertSame( $expectedRadius, $styles['borderRadius'] );
		$this->assertIsInt( $styles['borderRadius'] );
	}

	public function shapeProvider(): array {
		return array(
			'pill shape'                          => array( 'pill', 24 ),
			'rect shape'                          => array( 'rect', 4 ),
			'unknown shape falls back to default' => array( 'hexagon', 24 ),
			'empty shape falls back to default'   => array( '', 24 ),
		);
	}

	/**
	 * GIVEN the button label normalizes to the "buy" type
	 * WHEN building the styles for a given page context
	 * THEN "buy" is substituted with "pay" only for the mini cart
	 *
	 * @dataProvider buyTypeContextProvider
	 */
	public function testMiniCartSubstitutesBuyWithPay( string $context, string $expectedType ): void {
		$styling = $this->stylingWithLabel( 'buynow', $context );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( $context )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( $context );

		$this->assertSame( $expectedType, $styles['type'] );
	}

	public function buyTypeContextProvider(): array {
		return array(
			'mini cart substitutes buy with pay' => array( 'mini-cart', 'pay' ),
			'other contexts keep buy type'       => array( 'checkout', 'buy' ),
		);
	}

	/**
	 * GIVEN a WooCommerce Blocks express row context, where every wallet's button
	 * shares one row
	 * WHEN building the Google Pay button styles, whatever label the merchant configured
	 * THEN the type is forced to "plain" because a labelled button would be clipped
	 *
	 * @dataProvider expressRowContextAndLabelProvider
	 */
	public function testExpressRowForcesPlainType( string $context, string $label ): void {
		$styling = $this->stylingWithLabel( $label, $context );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( $context )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( $context );

		$this->assertSame( 'plain', $styles['type'] );
	}

	public function expressRowContextAndLabelProvider(): array {
		return array(
			'cart-block with buy label'        => array( 'cart-block', 'buynow' ),
			'cart-block with pay label'        => array( 'cart-block', 'pay' ),
			'cart-block with paypal label'     => array( 'cart-block', 'paypal' ),
			'checkout-block with buy label'    => array( 'checkout-block', 'buynow' ),
			'checkout-block with pay label'    => array( 'checkout-block', 'pay' ),
			'checkout-block with paypal label' => array( 'checkout-block', 'paypal' ),
		);
	}

	/**
	 * GIVEN a page context that is not part of the WooCommerce Blocks express row
	 * WHEN building the Google Pay button styles
	 * THEN the merchant's configured label is mapped as usual
	 *
	 * @dataProvider nonExpressRowContextLabelProvider
	 */
	public function testNonExpressRowStillMapsLabel( string $context, string $label, string $expectedType ): void {
		$styling = $this->stylingWithLabel( $label, $context );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( $context )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( $context );

		$this->assertSame( $expectedType, $styles['type'] );
	}

	public function nonExpressRowContextLabelProvider(): array {
		return array(
			'checkout context with buy label'    => array( 'checkout', 'buynow', 'buy' ),
			'checkout context with paypal label' => array( 'checkout', 'paypal', 'plain' ),
			'product context with buy label'     => array( 'product', 'buynow', 'buy' ),
			'product context with pay label'     => array( 'product', 'pay', 'pay' ),
		);
	}

	/**
	 * GIVEN the button label normalizes to the "buy" type
	 * WHEN building styles for the mini cart versus a block express row
	 * THEN the mini cart still substitutes "pay", unaffected by the express row
	 * handling, which forces "plain" instead
	 *
	 * @dataProvider miniCartAndExpressRowProvider
	 */
	public function testMiniCartSubstitutionIsUnaffectedByExpressRowHandling( string $context, string $expectedType ): void {
		$styling = $this->stylingWithLabel( 'buynow', $context );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( $context )->andReturn( $styling );
		$this->buttonLanguage();

		$styles = $this->configFor()->styles( $context );

		$this->assertSame( $expectedType, $styles['type'] );
	}

	public function miniCartAndExpressRowProvider(): array {
		return array(
			'mini cart still substitutes buy with pay' => array( 'mini-cart', 'pay' ),
			'cart-block forces plain instead'          => array( 'cart-block', 'plain' ),
			'checkout-block forces plain instead'      => array( 'checkout-block', 'plain' ),
		);
	}

	/**
	 * GIVEN the express row context forces the button type to "plain"
	 * WHEN building the Google Pay button styles
	 * THEN the color, language and border radius still reflect the merchant's settings
	 *
	 * @dataProvider expressRowContextProvider
	 */
	public function testExpressRowKeepsOtherStylesUnchanged( string $context ): void {
		$styling = $this->styling( $context, true, array(), 'pill', 'buynow', 'gold' );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( $context )->andReturn( $styling );
		$this->buttonLanguage( 'en_US' );

		$styles = $this->configFor()->styles( $context );

		$this->assertSame( 'black', $styles['color'] );
		$this->assertSame( 'en', $styles['language'] );
		$this->assertSame( 24, $styles['borderRadius'] );
	}

	public function expressRowContextProvider(): array {
		return array(
			'cart-block context'     => array( 'cart-block' ),
			'checkout-block context' => array( 'checkout-block' ),
		);
	}
}
