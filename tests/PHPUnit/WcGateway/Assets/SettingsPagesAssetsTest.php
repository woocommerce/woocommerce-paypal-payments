<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;
use Mockery;

class SettingsPagesAssetsTest extends TestCase
{
	public function testRegisterAssets()
	{
		$moduleUrl = 'http://example.com/wp-content/plugins/woocommerce-paypal-payments/modules/ppcp-wc-gateway';
		$modulePath = '/var/www/html/wp-content/plugins/woocommerce-paypal-payments/modules/ppcp-wc-gateway';
        $bearer = Mockery::mock(Bearer::class);

		$testee = new SettingsPageAssets($moduleUrl, $modulePath, $bearer);

		when('is_admin')
			->justReturn(true);
		when('is_ajax')
			->justReturn(false);

		$testee->register_assets();

		self::assertSame(has_action('admin_enqueue_scripts', "function()"), 10);
	}
}
