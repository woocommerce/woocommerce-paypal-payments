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

class InsightsServicesTest extends TestCase
{
    /**
     * GIVEN the merchant is connected to the sandbox or production environment
     * WHEN the 'axo.insights.script-url' service is resolved
     * THEN it returns the PayPal Insights script URL matching that environment
     *
     * @dataProvider script_url_provider
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
     * GIVEN a visitor with or without a customer session
     * WHEN the 'axo.insights' service is resolved
     * THEN the session ID is derived from the customer ID, or empty
     *
     * @dataProvider session_provider
     */
    public function test_insights_session_id_is_derived_from_customer_session(?object $session, string $expected): void
    {
        $merchant_data = new MerchantConnectionDTO(false, 'client-id', 'secret', 'merchant-id');

        $settings_provider = Mockery::mock(SettingsProvider::class);
        $settings_provider->allows('merchant_data')->andReturn($merchant_data);

        $currency = Mockery::mock(CurrencyGetter::class);
        $currency->allows('get')->andReturn('EUR');

        $container_map = [
            'settings.settings-provider'     => $settings_provider,
            'api.shop.currency.getter'       => $currency,
            'axo.payment_method_selected_map' => array(),
        ];
        $container     = Mockery::mock(ContainerInterface::class);
        $container->allows('get')->andReturnUsing(static fn(string $id) => $container_map[$id]);

        $wc = new \stdClass();
        if ($session !== null) {
            $wc->session = $session;
        }
        when('WC')->justReturn($wc);

        $services = require ROOT_DIR . '/modules/ppcp-axo/services.php';

        $factory  = $services['axo.insights'];
        $insights = $factory($container);

        $this->assertSame($expected, $insights['session_id']);
    }

    public function session_provider(): array
    {
        return [
            'session id is the first 16 chars of the md5 of the customer id' => [
                new InsightsSessionDouble('customer-123'),
                substr(md5('customer-123'), 0, 16),
            ],
            'no session gives an empty session id'                           => [null, ''],
            'session without a customer id gives an empty session id'        => [new \stdClass(), ''],
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

class InsightsSessionDouble
{
    private string $customer_unique_id;

    public function __construct(string $customer_unique_id)
    {
        $this->customer_unique_id = $customer_unique_id;
    }

    public function get_customer_unique_id(): string
    {
        return $this->customer_unique_id;
    }
}
