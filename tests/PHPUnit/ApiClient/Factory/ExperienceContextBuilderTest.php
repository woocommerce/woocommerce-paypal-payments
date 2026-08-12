<?php
declare(strict_types=1);

namespace PHPUnit\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Shipping\ShippingCallbackUrlFactory;
use function Brain\Monkey\Functions\expect;
use Mockery;

class ExperienceContextBuilderTest extends TestCase
{
	private $settings;

	private $shipping_callback_url_factory;

	private $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings = Mockery::mock(SettingsProvider::class);
		$this->shipping_callback_url_factory = Mockery::mock(ShippingCallbackUrlFactory::class);

		$this->sut = new ExperienceContextBuilder($this->settings, $this->shipping_callback_url_factory);
	}

	public function testOrderReturnUrls()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder
			->expects('get_checkout_order_received_url')
			->andReturn('https://example.com');

		expect('add_query_arg')->andReturnUsing(function ($key, $value, $url) {
			return "{$url}?{$key}={$value}";
		});

		$result = $this->sut
			->with_order_return_urls($wcOrder)
			->build();

		self::assertEquals([
			'return_url' => 'https://example.com',
			'cancel_url' => 'https://example.com?cancelled=true',
		], $result->to_array());
	}

	public function testCurrentLocale()
	{
		expect('get_user_locale')->andReturn('de-DE-formal');

		$result = $this->sut
			->with_current_locale()
			->build();

		self::assertEquals([
			'locale' => 'de-DE',
		], $result->to_array());
	}

	/**
	 * @dataProvider brandNameDataProvider
	 */
	public function testCurrentBrandName($value, $expected)
	{

		$this->settings
			->expects('brand_name')
			->andReturn($value);

		$result = $this->sut
			->with_current_brand_name()
			->build();

		if ($expected === null) {
			$this->assertEmpty($result->to_array());
			return;
		}

		self::assertEquals([
			'brand_name' => $expected,
		], $result->to_array());
	}

	public function brandNameDataProvider()
	{
		yield [
			'',
			null,
		];
		yield [
			'company',
			'company',
		];
	}

	/**
	 * @dataProvider landingPageDataProvider
	 */
	public function testCurrentLandingPage($value, $expected)
	{
		$this->settings
			->expects('landing_page_enum')
			->andReturn($value);

		$result = $this->sut
			->with_current_landing_page()
			->build();

		self::assertEquals([
			'landing_page' => $expected,
		], $result->to_array());
	}

	public function landingPageDataProvider()
	{
		yield [
			'',
			ExperienceContext::LANDING_PAGE_NO_PREFERENCE,
		];
		yield [
			ExperienceContext::LANDING_PAGE_LOGIN,
			ExperienceContext::LANDING_PAGE_LOGIN,
		];
	}

	/**
	 * @dataProvider paymentMethodPreferenceDataProvider
	 */
	public function testCurrentPaymentMethodPreference( $value, $expected)
	{
		$this->settings
			->expects('instant_payments_only')
			->andReturn($value);

		$result = $this->sut
			->with_current_payment_method_preference()
			->build();

		self::assertEquals([
			'payment_method_preference' => $expected,
		], $result->to_array());
	}

	public function paymentMethodPreferenceDataProvider()
	{
		yield [
			'',
			ExperienceContext::PAYMENT_METHOD_UNRESTRICTED,
		];
		yield [
			'yes',
			ExperienceContext::PAYMENT_METHOD_IMMEDIATE_PAYMENT_REQUIRED,
		];
	}

	/**
	 * @dataProvider contactPreferenceDataProvider
	 */
	public function testContactPreference($preference) {
		$result = $this->sut
			->with_contact_preference($preference)
			->build();

		self::assertEquals([
			'contact_preference' => $preference,
		], $result->to_array());
	}

	public function contactPreferenceDataProvider()
	{
		yield [
			ExperienceContext::CONTACT_PREFERENCE_NO_CONTACT_INFO,
		];
		yield [
			ExperienceContext::CONTACT_PREFERENCE_UPDATE_CONTACT_INFO,
		];
	}

	/**
	 * Builds an ExperienceContextBuilder wired with a ReturnUrlSecret double.
	 *
	 * @param \Mockery\MockInterface $return_url_secret Double for ApiClient\Helper\ReturnUrlSecret.
	 */
	private function create_sut_with_return_url_secret($return_url_secret): ExperienceContextBuilder
	{
		return new ExperienceContextBuilder(
			$this->settings,
			$this->shipping_callback_url_factory,
			$return_url_secret
		);
	}

	/**
	 * GIVEN a pending return-url secret issued by ReturnUrlSecret::issue_pending()
	 * WHEN with_endpoint_return_urls() builds the WC-AJAX return URL
	 * THEN the return URL carries a ppcp_return_nonce query argument
	 * AND its value is exactly the secret that was issued
	 *
	 * @scenario The return URL that PayPal redirects the buyer back to must carry
	 *           proof (a single-use secret) that the requester started the flow,
	 *           closing the token-replay gap described in
	 *           bug:wc-gateway:return-url-token-replay.
	 * @covers \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder::with_endpoint_return_urls
	 */
	public function test_endpoint_return_url_carries_the_issued_pending_nonce(): void
	{
		// Arrange
		$issued_secret = 'pending-secret-2f9b6c1a';

		$return_url_secret = Mockery::mock( \WooCommerce\PayPalCommerce\ApiClient\Helper\ReturnUrlSecret::class );
		$return_url_secret->allows( 'issue_pending' )->andReturn( $issued_secret );

		expect( 'home_url' )->andReturn( 'https://example.com/wc-ajax/ppc-return-url' );
		expect( 'wc_get_checkout_url' )->andReturn( 'https://example.com/checkout' );
		expect( 'add_query_arg' )->andReturnUsing( function ( $key, $value, $url ) {
			$separator = strpos( $url, '?' ) === false ? '?' : '&';
			return $url . $separator . $key . '=' . $value;
		} );

		$sut = $this->create_sut_with_return_url_secret( $return_url_secret );

		// When
		$result = $sut->with_endpoint_return_urls()->build();
		$return_url = $result->to_array()['return_url'] ?? '';

		// Then
		$query = [];
		parse_str( (string) parse_url( $return_url, PHP_URL_QUERY ), $query );

		self::assertSame( $issued_secret, $query['ppcp_return_nonce'] ?? null );
	}
}
