<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AxoBlock;

use Brain\Monkey\Actions;
use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use function Brain\Monkey\Functions\when;

class AxoBlockModuleInsightsScriptTest extends TestCase
{
    private const HANDLE = 'wc-ppcp-paypal-insights';

    /**
     * GIVEN a visitor on a page, with Fastlane on or off
     * WHEN the page enqueues its scripts
     * THEN the Insights script is enqueued only where block tracking applies
     *
     * @dataProvider insights_enqueue_provider
     */
    public function test_insights_script_is_enqueued_only_where_fastlane_block_tracking_applies(
        bool $has_checkout_block,
        bool $is_logged_in,
        bool $is_order_pay,
        bool $cart_is_empty,
        bool $use_fastlane,
        bool $expect_enqueued
    ): void {
        when('has_block')->alias(
            static fn($block = '') => $has_checkout_block && 'woocommerce/checkout' === $block
        );
        when('is_user_logged_in')->justReturn($is_logged_in);
        when('is_checkout_pay_page')->justReturn($is_order_pay);
        when('wp_register_style')->justReturn(true);
        when('wp_enqueue_style')->justReturn(true);
        when('wp_register_script')->justReturn(true);
        when('wp_localize_script')->justReturn(true);

        $cart = Mockery::mock();
        $cart->allows('is_empty')->andReturn($cart_is_empty);
        $wc       = Mockery::mock();
        $wc->cart = $cart;
        when('WC')->justReturn($wc);

        $enqueued = array();
        when('wp_enqueue_script')->alias(
            static function (string $handle) use (&$enqueued): void {
                $enqueued[] = $handle;
            }
        );

        foreach ($this->capture_enqueue_scripts_callbacks($use_fastlane) as $callback) {
            $callback();
        }

        $this->assertSame($expect_enqueued ? array(self::HANDLE) : array(), $enqueued);
    }

    public function insights_enqueue_provider(): array
    {
        return [
            'guest on checkout block with items enqueues the script' => [true, false, false, false, true, true],
            'logged-in user on checkout block does not enqueue' => [true, true, false, false, true, false],
            'guest on order-pay does not enqueue' => [true, false, true, false, true, false],
            'guest with empty cart does not enqueue' => [true, false, false, true, true, false],
            'guest on page without checkout block does not enqueue' => [false, false, false, false, true, false],
            'fastlane off for guest on checkout block does not enqueue' => [true, false, false, false, false, false],
        ];
    }

    private function capture_enqueue_scripts_callbacks(bool $use_fastlane): array
    {
        $card_configuration = Mockery::mock(CardPaymentsConfiguration::class);
        $card_configuration->allows('use_fastlane')->andReturn($use_fastlane);

        $asset_getter = Mockery::mock(AssetGetter::class);
        $asset_getter->allows('get_asset_url')->andReturn('https://example.test/asset.js');

        $services = [
            'axo.eligible' => true,
            'axoblock.asset_getter' => $asset_getter,
            'ppcp.asset-version' => '1.0.0',
            'wcgateway.configuration.card-configuration' => $card_configuration,
        ];

        $container = Mockery::mock(ContainerInterface::class);
        $container->allows('get')->andReturnUsing(static fn(string $id) => $services[$id]);
        $container->allows('has')->andReturnUsing(static fn(string $id) => isset($services[$id]));

        $callbacks = array();
        Actions\expectAdded('wp_enqueue_scripts')->whenHappen(
            static function (callable $callback) use (&$callbacks): void {
                $callbacks[] = $callback;
            }
        );

        (new AxoBlockModule())->run($container);

        $this->assertNotEmpty($callbacks);

        return $callbacks;
    }
}
