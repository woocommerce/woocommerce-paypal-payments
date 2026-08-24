<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;

class V6PaymentMethodTest extends TestCase
{
    private $manager;
    private $asset_getter;
    private $gateway;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager      = Mockery::mock(SdkV6Manager::class);
        $this->asset_getter = Mockery::mock(AssetGetter::class);
        $this->gateway      = Mockery::mock(PayPalGateway::class);
    }

    private function createTestee(): V6PaymentMethod
    {
        return new V6PaymentMethod(
            $this->manager,
            $this->asset_getter,
            '1.0.0',
            $this->gateway
        );
    }

    /**
     * GIVEN a compiled checkout-block bundle whose webpack asset file records the
     *       @wordpress/* script handles it depends on and a content-hash version
     * WHEN the block payment method resolves its script handles
     * THEN the script is registered with those exact dependencies and version, not an
     *      empty dependency list and not just the plugin version.
     * AND the resolved handle is returned so WooCommerce Blocks loads it
     */
    public function test_get_payment_method_script_handles_passes_through_webpack_dependencies_and_version(): void
    {
        $this->asset_getter->shouldReceive('get_asset_url')
            ->with('checkout-block.js')
            ->andReturn('https://example.com/assets/checkout-block.js');
        $this->asset_getter->shouldReceive('get_asset_data')
            ->with('checkout-block.js', '1.0.0')
            ->andReturn(['dependencies' => ['wp-data', 'wp-element'], 'version' => 'deadbeef']);

        expect('wp_register_script')
            ->once()
            ->with(
                'wc-ppcp-sdk-v6-blocks',
                'https://example.com/assets/checkout-block.js',
                ['wp-data', 'wp-element'],
                'deadbeef',
                true
            );

        $testee  = $this->createTestee();
        $handles = $testee->get_payment_method_script_handles();

        $this->assertSame(['wc-ppcp-sdk-v6-blocks'], $handles);
    }

    /**
     * GIVEN no compiled checkout-block bundle URL is available
     * WHEN the block payment method resolves its script handles
     * THEN no script is registered and an empty handle list is returned
     */
    public function test_get_payment_method_script_handles_returns_empty_array_when_no_asset_url(): void
    {
        $this->asset_getter->shouldReceive('get_asset_url')
            ->with('checkout-block.js')
            ->andReturn('');

        expect('wp_register_script')->never();

        $testee  = $this->createTestee();
        $handles = $testee->get_payment_method_script_handles();

        $this->assertSame([], $handles);
    }
}
