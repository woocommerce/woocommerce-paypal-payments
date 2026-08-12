<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;

class GooglePayConfigTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @var SettingsProvider&Mockery\MockInterface
	 */
	private $provider;

	public function setUp(): void {
		parent::setUp();

		$this->provider = Mockery::mock( SettingsProvider::class );
		$this->provider->shouldReceive( 'googlepay_button_language' )->andReturn( 'en' );
	}

	private function configFor(): GooglePayConfig {
		return new GooglePayConfig( $this->provider );
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

	private function stylingEnabled(): LocationStylingDTO {
		return $this->styling( 'checkout', true, array( GooglePayGateway::ID ), 'rect', 'pay', 'black' );
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
	 * THEN it is reported as not enabled, regardless of the location's own styling
	 */
	public function testDisabledWhenGatewayIsOffGlobally(): void {
		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( false );
		$this->provider->shouldNotReceive( 'googlepay_styles' );

		$this->assertFalse( $this->configFor()->enabled( 'checkout' ) );
	}

	/**
	 * GIVEN the gateway is on globally but the location's styling marks it disabled
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as not enabled
	 */
	public function testDisabledWhenLocationStylingIsDisabled(): void {
		$styling = $this->stylingDisabled();

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$this->assertFalse( $this->configFor()->enabled( 'checkout' ) );
	}

	/**
	 * GIVEN the gateway and location are enabled but Google Pay is not among the
	 * location's active payment methods
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as not enabled
	 */
	public function testDisabledWhenGatewayIdMissingFromMethods(): void {
		$styling = $this->stylingWithoutGooglePayMethod();

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$this->assertFalse( $this->configFor()->enabled( 'checkout' ) );
	}

	/**
	 * GIVEN the gateway is on globally, the location is enabled, and Google Pay is
	 * among the location's active payment methods
	 * WHEN checking whether it should render in that context
	 * THEN it is reported as enabled
	 */
	public function testEnabledWhenGloballyOnLocationOnAndMethodPresent(): void {
		$styling = $this->stylingEnabled();

		$this->provider->shouldReceive( 'googlepay_enabled' )->andReturn( true );
		$this->provider->shouldReceive( 'googlepay_styles' )->with( 'checkout' )->andReturn( $styling );

		$this->assertTrue( $this->configFor()->enabled( 'checkout' ) );
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
		$this->provider->shouldReceive( 'googlepay_button_language' )->andReturn( $inputLanguage );

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
	 * THEN "buy" is substituted with "pay" only for the mini cart, which is too narrow for the longer label
	 *
	 * @dataProvider buyTypeContextProvider
	 */
	public function testMiniCartSubstitutesBuyWithPay( string $context, string $expectedType ): void {
		$styling = $this->stylingWithLabel( 'buynow', $context );

		$this->provider->shouldReceive( 'googlepay_styles' )->with( $context )->andReturn( $styling );

		$styles = $this->configFor()->styles( $context );

		$this->assertSame( $expectedType, $styles['type'] );
	}

	public function buyTypeContextProvider(): array {
		return array(
			'mini cart substitutes buy with pay' => array( 'mini-cart', 'pay' ),
			'other contexts keep buy type'       => array( 'checkout', 'buy' ),
		);
	}
}
