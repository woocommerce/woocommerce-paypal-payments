<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\Helper\SettingsStub;
use WooCommerce\PayPalCommerce\ModularTestCase;

class LocationsTest extends ModularTestCase
{
	private $appContainer;

	private $settings;

	public function setUp(): void {
		parent::setUp();

		$this->settings = new SettingsStub([]);

		$this->appContainer = $this->bootstrapModule([
			'wcgateway.settings' => function () {
				return $this->settings;
			},
		]);
	}

	/**
	 * @dataProvider payLaterButtonLocationsData
	 */
	public function testPayLaterButtonLocations(array $selectedLocations, array $expectedResult) {
		$this->settings->set('smart_button_locations', $selectedLocations);

		$result = $this->appContainer->get('wcgateway.settings.pay-later.button-locations');

		self::assertEquals($expectedResult, $result);
	}

	public function payLaterButtonLocationsData()
	{
		yield [
			['product', 'cart', 'checkout', 'mini-cart'],
			[
				'product' => 'Single Product',
				'cart' => 'Cart',
				'checkout' => 'Checkout',
				'mini-cart' => 'Mini Cart',
			],
		];
		yield [
			['cart', 'checkout'],
			[
				'cart' => 'Cart',
				'checkout' => 'Checkout',
			],
		];
		yield [
			[],
			[],
		];
	}
}
