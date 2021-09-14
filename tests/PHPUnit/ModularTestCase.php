<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use Dhii\Container\CompositeCachingServiceProvider;
use Dhii\Container\DelegatingContainer;
use Dhii\Container\ServiceProvider;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\when;

class ModularTestCase extends TestCase
{
	use MockeryPHPUnitIntegration;

    public function setUp(): void
    {
        parent::setUp();

        when('get_option')->justReturn(null);
        when('plugins_url')->returnArg();
        when('plugin_dir_path')->alias(function ($file) { return trailingslashit(dirname($file)); });
        when('get_current_blog_id')->justReturn(42);
        when('get_site_url')->justReturn('example.com');
        when('get_bloginfo')->justReturn('My Shop');
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('WC')->justReturn((object) [
        	'session' => null,
		]);

		global $wpdb;
		$wpdb = \Mockery::mock(\stdClass::class);
		$wpdb->shouldReceive('get_var')->andReturn(null);
		$wpdb->shouldReceive('prepare')->andReturn(null);
		$wpdb->posts = '';
		$wpdb->postmeta = '';

		!defined('PAYPAL_API_URL') && define('PAYPAL_API_URL', 'https://api.paypal.com');
		!defined('PAYPAL_SANDBOX_API_URL') && define('PAYPAL_SANDBOX_API_URL', 'https://api.sandbox.paypal.com');
		!defined('PAYPAL_INTEGRATION_DATE') && define('PAYPAL_INTEGRATION_DATE', '2020-10-15');

		!defined('PPCP_FLAG_SUBSCRIPTION') && define('PPCP_FLAG_SUBSCRIPTION', true);

		!defined('CONNECT_WOO_CLIENT_ID') && define('CONNECT_WOO_CLIENT_ID', 'woo-id');
		!defined('CONNECT_WOO_SANDBOX_CLIENT_ID') && define('CONNECT_WOO_SANDBOX_CLIENT_ID', 'woo-id2');
		!defined('CONNECT_WOO_MERCHANT_ID') && define('CONNECT_WOO_MERCHANT_ID', 'merchant-id');
		!defined('CONNECT_WOO_SANDBOX_MERCHANT_ID') && define('CONNECT_WOO_SANDBOX_MERCHANT_ID', 'merchant-id2');
		!defined('CONNECT_WOO_URL') && define('CONNECT_WOO_URL', 'https://connect.woocommerce.com/ppc');
		!defined('CONNECT_WOO_SANDBOX_URL') && define('CONNECT_WOO_SANDBOX_URL', 'https://connect.woocommerce.com/ppcsandbox');
    }

    /**
     * @param array<string, callable> $overriddenServices
     * @return ContainerInterface
     */
    protected function bootstrapModule(array $overriddenServices = []): ContainerInterface
    {
        $overridingContainer = new DelegatingContainer(new CompositeCachingServiceProvider([
            new ServiceProvider($overriddenServices, []),
        ]));

        $rootDir = ROOT_DIR;
        $bootstrap = require ("$rootDir/bootstrap.php");
        $appContainer = $bootstrap($rootDir, $overridingContainer);

        return $appContainer;
    }
}
