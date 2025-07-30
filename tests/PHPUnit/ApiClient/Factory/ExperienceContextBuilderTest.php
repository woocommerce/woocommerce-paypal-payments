<?php
declare(strict_types=1);

namespace PHPUnit\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
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

		$this->settings = Mockery::mock(Settings::class);
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
	public function testCurrentBrandName($has, $value, $expected)
	{
		$this->settings
			->expects('has')
			->with('brand_name')
			->andReturn($has);
		if ($has) {
			$this->settings
				->expects('get')
				->with('brand_name')
				->andReturn($value);
		}

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
			false,
			'',
			null,
		];
		yield [
			true,
			'',
			null,
		];
		yield [
			true,
			'company',
			'company',
		];
	}

	/**
	 * @dataProvider landingPageDataProvider
	 */
	public function testCurrentLandingPage($has, $value, $expected)
	{
		$this->settings
			->expects('has')
			->with('landing_page')
			->andReturn($has);
		if ($has) {
			$this->settings
				->expects('get')
				->with('landing_page')
				->andReturn($value);
		}

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
			false,
			'',
			ExperienceContext::LANDING_PAGE_NO_PREFERENCE,
		];
		yield [
			true,
			'',
			ExperienceContext::LANDING_PAGE_NO_PREFERENCE,
		];
		yield [
			true,
			ExperienceContext::LANDING_PAGE_LOGIN,
			ExperienceContext::LANDING_PAGE_LOGIN,
		];
	}

	/**
	 * @dataProvider paymentMethodPreferenceDataProvider
	 */
	public function testCurrentPaymentMethodPreference($has, $value, $expected)
	{
		$this->settings
			->expects('has')
			->with('payee_preferred')
			->andReturn($has);
		if ($has) {
			$this->settings
				->expects('get')
				->with('payee_preferred')
				->andReturn($value);
		}

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
			false,
			'',
			ExperienceContext::PAYMENT_METHOD_UNRESTRICTED,
		];
		yield [
			true,
			'',
			ExperienceContext::PAYMENT_METHOD_UNRESTRICTED,
		];
		yield [
			true,
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
