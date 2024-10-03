<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingAgreementsEndpoint;
use WooCommerce\PayPalCommerce\Helper\CurrencyGetterStub;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;
use Mockery;

class SettingsPagesAssetsTest extends TestCase
{
	public function testRegisterAssets()
	{
		$moduleUrl = 'http://example.com/wp-content/plugins/woocommerce-paypal-payments/modules/ppcp-wc-gateway';
		$modulePath = '/var/www/html/wp-content/plugins/woocommerce-paypal-payments/modules/ppcp-wc-gateway';
		$subscriptionsHelper = Mockery::mock(SubscriptionHelper::class);
		$billingAgreementEndpoint = Mockery::mock(BillingAgreementsEndpoint::class);


		$testee = new SettingsPageAssets(
		    $moduleUrl,
            $modulePath,
            $subscriptionsHelper,
            '123',
            new CurrencyGetterStub(),
            'DE',
			Mockery::mock(Environment::class),
            true,
            array(),
            array(),
			true,
			false,
			$billingAgreementEndpoint,
			true
		);

		when('is_admin')
			->justReturn(true);
		when('wp_doing_ajax')
			->justReturn(false);

		$testee->register_assets();

		self::assertSame(has_action('admin_enqueue_scripts', "function()"), 10);
	}
}
