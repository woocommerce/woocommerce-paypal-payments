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
	 * GIVEN a builder with no landing page set
	 * WHEN with_landing_page() is called with an explicit value
	 * THEN the resulting context carries that value, ignoring the merchant setting
	 */
	public function testLandingPageSetsExplicitValue()
	{
		$this->settings->shouldNotReceive('landing_page_enum');

		$result = $this->sut
			->with_landing_page(ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT)
			->build();

		self::assertEquals([
			'landing_page' => ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT,
		], $result->to_array());
	}

	/**
	 * GIVEN a builder
	 * WHEN with_landing_page() is called
	 * THEN the original builder instance is left unmodified
	 */
	/**
	 * GIVEN a builder that already applied the merchant's current landing page setting
	 * WHEN with_landing_page() is called afterwards with an explicit value
	 * THEN the explicit value overrides the merchant setting
	 */
	public function testLandingPageOverridesCurrentLandingPage()
	{
		$this->settings
			->expects('landing_page_enum')
			->andReturn(ExperienceContext::LANDING_PAGE_LOGIN);

		$result = $this->sut
			->with_current_landing_page()
			->with_landing_page(ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT)
			->build();

		self::assertEquals([
			'landing_page' => ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT,
		], $result->to_array());
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
}
