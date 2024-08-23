<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use WooCommerce\PayPalCommerce\Helper\RedirectorStub;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\when;

class ModularTestCase extends TestCase
{
	use MockeryPHPUnitIntegration;

    public function setUp(): void
    {
        parent::setUp();

        when('get_option')->justReturn(null);
        when('admin_url')->returnArg();
        when('plugins_url')->returnArg();
        when('plugin_dir_path')->alias(function ($file) { return trailingslashit(dirname($file)); });
		when('is_plugin_active')->justReturn(true);
        when('get_current_blog_id')->justReturn(42);
        when('get_site_url')->justReturn('example.com');
        when('get_bloginfo')->justReturn('My Shop');
		when('get_woocommerce_currency')->justReturn('USD');
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('WC')->justReturn((object) [
        	'session' => null,
		]);

		global $wpdb;
		$wpdb = \Mockery::mock(\stdClass::class);
		$wpdb->shouldReceive('get_var')->andReturn(null);
		$wpdb->shouldReceive('prepare')->andReturn(null);
		$wpdb->posts = '';
		$wpdb->postmeta = '';

		!defined('PAYPAL_API_URL') && define('PAYPAL_API_URL', 'https://api-m.paypal.com');
		!defined('PAYPAL_URL') && define( 'PAYPAL_URL', 'https://www.paypal.com' );
		!defined('PAYPAL_SANDBOX_API_URL') && define('PAYPAL_SANDBOX_API_URL', 'https://api-m.sandbox.paypal.com');
		!defined('PAYPAL_SANDBOX_URL') && define( 'PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com' );
		!defined('PAYPAL_INTEGRATION_DATE') && define('PAYPAL_INTEGRATION_DATE', '2020-10-15');

		!defined('PPCP_FLAG_SUBSCRIPTION') && define('PPCP_FLAG_SUBSCRIPTION', true);

		!defined('CONNECT_WOO_CLIENT_ID') && define('CONNECT_WOO_CLIENT_ID', 'woo-id');
		!defined('CONNECT_WOO_SANDBOX_CLIENT_ID') && define('CONNECT_WOO_SANDBOX_CLIENT_ID', 'woo-id2');
		!defined('CONNECT_WOO_MERCHANT_ID') && define('CONNECT_WOO_MERCHANT_ID', 'merchant-id');
		!defined('CONNECT_WOO_SANDBOX_MERCHANT_ID') && define('CONNECT_WOO_SANDBOX_MERCHANT_ID', 'merchant-id2');
		!defined('CONNECT_WOO_URL') && define('CONNECT_WOO_URL', 'https://connect.woocommerce.com/ppc');
		!defined('CONNECT_WOO_SANDBOX_URL') && define('CONNECT_WOO_SANDBOX_URL', 'https://connect.woocommerce.com/ppcsandbox');
		!defined('PPCP_PAYPAL_BN_CODE') && define('PPCP_PAYPAL_BN_CODE', 'Woo_PPCP');
    }

    /**
     * @param array<string, callable> $overriddenServices
     * @return ContainerInterface
     */
    protected function bootstrapModule(array $overriddenServices = []): ContainerInterface
    {
		$overriddenServices = array_merge([
			'http.redirector' => function () {
				return new RedirectorStub();
			}
		], $overriddenServices);

		$module = new class ($overriddenServices) implements ServiceModule, ExecutableModule {
			use ModuleClassNameIdTrait;

			public function __construct(array $services) {
				$this->services = $services;
			}

			public function services(): array {
				return $this->services;
			}

			public function run(ContainerInterface $c): bool {
				return true;
			}
		};

        $rootDir = ROOT_DIR;
        $bootstrap = require ("$rootDir/bootstrap.php");
        $appContainer = $bootstrap($rootDir, [], [$module]);

		PPCP::init($appContainer);

        return $appContainer;
    }
}
