<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use function Brain\Monkey\Functions\when;

/**
 * Exercises the 'axo.insights.script-url' service as defined in
 * modules/ppcp-axo/services.php: the container closure is loaded from the real
 * production file and invoked against a stub container.
 */
class InsightsServicesTest extends TestCase
{
    /**
     * @dataProvider script_url_provider
     *
     * GIVEN the merchant is connected to the sandbox or production environment
     * WHEN the 'axo.insights.script-url' service is resolved
     * THEN it returns the PayPal Insights script URL matching that environment
     */
    public function test_insights_script_url_matches_environment(bool $is_sandbox, string $expected): void
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')
            ->with('settings.environment')
            ->andReturn(new Environment($is_sandbox));

        $services = require ROOT_DIR . '/modules/ppcp-axo/services.php';

        $factory = $services['axo.insights.script-url'];

        $this->assertSame($expected, $factory($container));
    }

    /**
     * @dataProvider insights_enabled_provider
     *
     * GIVEN the 'axo.insights.enabled' service reports Insights as enabled or disabled
     * WHEN the 'axo.insights' service is resolved
     * THEN its 'enabled' flag reflects that decision
     */
    public function test_insights_data_enabled_flag_follows_enabled_service(bool $enabled): void
    {
        $merchant_data = new MerchantConnectionDTO(false, 'client-id', 'secret', 'merchant-id');

        $settings_provider = Mockery::mock(SettingsProvider::class);
        $settings_provider->shouldReceive('merchant_data')->andReturn($merchant_data);

        $currency = Mockery::mock(CurrencyGetter::class);
        $currency->shouldReceive('get')->andReturn('EUR');

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with('settings.settings-provider')->andReturn($settings_provider);
        $container->shouldReceive('get')->with('api.shop.currency.getter')->andReturn($currency);
        $container->shouldReceive('get')->with('axo.insights.enabled')->andReturn($enabled);
        $container->shouldReceive('get')->with('axo.payment_method_selected_map')->andReturn(array());

        when('WC')->justReturn(new \stdClass());

        $services = require ROOT_DIR . '/modules/ppcp-axo/services.php';

        $factory = $services['axo.insights'];
        $insights = $factory($container);

        $this->assertSame($enabled, $insights['enabled']);
        $this->assertSame('client-id', $insights['client_id']);
    }

    public function insights_enabled_provider(): array
    {
        return [
            'insights enabled'  => [true],
            'insights disabled' => [false],
        ];
    }

    public function script_url_provider(): array
    {
        return [
            'sandbox uses the sandbox insights script' => [
                true,
                'https://www.paypalobjects.com/insights/v1/paypal-insights.sandbox.min.js',
            ],
            'production uses the live insights script' => [
                false,
                'https://www.paypalobjects.com/insights/v1/paypal-insights.min.js',
            ],
        ];
    }
}
