<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use Mockery;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;

class LocationsTest extends ModularTestCase
{
	private $appContainer;

	private $settingsProvider;

	private $selectedLocations = [];

	public function setUp(): void {
		parent::setUp();

		$this->settingsProvider = Mockery::mock(SettingsProvider::class)->shouldIgnoreMissing('');
		$this->settingsProvider->shouldReceive('smart_button_locations')
			->andReturnUsing(function () {
				return $this->selectedLocations;
			});

		$this->appContainer = $this->bootstrapModule([
			'settings.settings-provider' => function () {
				return $this->settingsProvider;
			},
		]);
	}

	/**
	 * @dataProvider payLaterButtonLocationsData
	 */
	public function testPayLaterButtonLocations(array $selectedLocations, array $expectedResult) {
		$this->selectedLocations = $selectedLocations;

		$result = $this->appContainer->get('wcgateway.settings.pay-later.button-locations');

		self::assertEquals($expectedResult, $result);
	}

	public function payLaterButtonLocationsData()
	{
		yield [
			['product', 'cart', 'checkout', 'mini-cart'],
			[
				'product' => 'Single Product',
				'cart' => 'Classic Cart',
				'checkout' => 'Classic Checkout',
				'mini-cart' => 'Mini Cart',
			],
		];
		yield [
			['cart', 'checkout'],
			[
				'cart' => 'Classic Cart',
				'checkout' => 'Classic Checkout',
			],
		];
		yield [
			[],
			[],
		];
	}
}
