<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Blocks\SdkV6EditorPreview
 */
class SdkV6EditorPreviewTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
    }

    /**
     * @param mixed $client_id
     */
    private function create_container(bool $v6_loaded, bool $is_sandbox = false, $client_id = 'client-id'): ContainerInterface
    {
        $environment = Mockery::mock(Environment::class);
        $environment->shouldReceive('is_sandbox')->andReturn($is_sandbox);

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')->with('sdk-v6.owns-current-page')->andReturn($v6_loaded);
        $container->shouldReceive('get')->with('settings.environment')->andReturn($environment);
        $container->shouldReceive('get')->with('button.client_id')->andReturn($client_id);

        return $container;
    }

    /**
     * GIVEN the SDK v6 module is not loaded
     * WHEN the editor preview config is requested
     * THEN null is returned, so the editor does not load SDK v6
     */
    public function testReturnsNullWhenV6ModuleIsNotLoaded(): void
    {
        $this->assertNull(SdkV6EditorPreview::config($this->create_container(false)));
    }

    /**
     * GIVEN the SDK v6 module is loaded for a live merchant
     * WHEN the editor preview config is requested
     * THEN the full config uses the live SDK URL, store currency and formatted locale
     */
    public function testReturnsFullConfigForLiveMerchant(): void
    {
        $config = SdkV6EditorPreview::config($this->create_container(true, false, 'live-client'));

        $this->assertSame(
            array(
                'sdkUrl' => 'https://www.paypal.com/web-sdk/v6/core',
                'clientId' => 'live-client',
                'currency' => 'USD',
                'locale' => 'en-US',
            ),
            $config
        );
    }

    /**
     * GIVEN the SDK v6 module is loaded
     * WHEN the editor preview config is requested in a given environment
     * THEN the SDK URL points to the matching PayPal host
     *
     * @dataProvider environment_provider
     */
    public function testSdkUrlDependsOnEnvironment(bool $is_sandbox, string $expected_url): void
    {
        $config = SdkV6EditorPreview::config($this->create_container(true, $is_sandbox));

        $this->assertSame($expected_url, $config['sdkUrl']);
    }

    public function environment_provider(): array
    {
        return array(
            'sandbox uses sandbox host' => array(true, 'https://www.sandbox.paypal.com/web-sdk/v6/core'),
            'live uses live host' => array(false, 'https://www.paypal.com/web-sdk/v6/core'),
        );
    }

    /**
     * GIVEN the client id is not a string
     * WHEN the editor preview config is requested
     * THEN the client id is returned as a string
     */
    public function testClientIdIsCastToString(): void
    {
        $config = SdkV6EditorPreview::config($this->create_container(true, false, 12345));

        $this->assertSame('12345', $config['clientId']);
    }

    /**
     * GIVEN the site uses a given WordPress locale
     * WHEN the editor preview config is requested
     * THEN the locale is formatted for SDK v6, or empty when there is no region
     *
     * @dataProvider locale_provider
     */
    public function testLocaleIsFormattedForSdkV6(string $wp_locale, string $expected): void
    {
        when('get_locale')->justReturn($wp_locale);

        $config = SdkV6EditorPreview::config($this->create_container(true));

        $this->assertSame($expected, $config['locale']);
    }

    public function locale_provider(): array
    {
        return array(
            'language and region' => array('en_US', 'en-US'),
            'formal variant drops suffix' => array('de_DE_formal', 'de-DE'),
            'bare language is empty' => array('ja', ''),
        );
    }

    /**
     * GIVEN the store uses a non-default currency
     * WHEN the editor preview config is requested
     * THEN the store currency is returned
     */
    public function testCurrencyComesFromStoreSettings(): void
    {
        when('get_woocommerce_currency')->justReturn('EUR');

        $config = SdkV6EditorPreview::config($this->create_container(true));

        $this->assertSame('EUR', $config['currency']);
    }
}
