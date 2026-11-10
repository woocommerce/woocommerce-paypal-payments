<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

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
