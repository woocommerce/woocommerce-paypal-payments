<?php

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use WooCommerce\PayPalCommerce\Compat\Settings\SettingsMap;
use WooCommerce\PayPalCommerce\Compat\Settings\SettingsMapHelper;
use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

class SettingsTest extends TestCase {
	private Settings $settings;

	protected function setUp(): void {
		$commonSettingsModel = $this->createMock(AbstractDataModel::class);
		$commonSettingsModel->method('to_array')->willReturn([
			'use_sandbox'   => 'yes',
			'client_id'     => 'abc123',
			'client_secret' => 'secret123',
		]);

		$generalSettingsModel = $this->createMock(AbstractDataModel::class);
		$generalSettingsModel->method('to_array')->willReturn([
			'is_sandbox'             => 'no',
			'live_client_id'         => 'live_id_123',
			'live_client_secret'     => 'live_secret_123',
		]);

		$settingsMap = [
			new SettingsMap(
				$commonSettingsModel,
				[
					'client_id'     => 'client_id',
					'client_secret' => 'client_secret',
				]
			),
			new SettingsMap(
				$generalSettingsModel,
				[
					'is_sandbox'             => 'sandbox_on',
					'live_client_id'         => 'client_id_production',
					'live_client_secret'     => 'client_secret_production',
				]
			),
		];

		$settingsMapHelper = new SettingsMapHelper($settingsMap);

		$this->settings = new Settings(
			['cart', 'checkout'],
			'PayPal Credit Gateway',
			['checkout'],
			['cart'],
			$settingsMapHelper
		);
	}

	public function testGetMappedValue() {
		$value = $this->settings->get('sandbox_on');

		$this->assertEquals('no', $value);
	}

	public function testGetThrowsNotFoundExceptionForInvalidKey() {
		$this->expectException(NotFoundException::class);

		$this->settings->get('invalid_key');
	}
}

